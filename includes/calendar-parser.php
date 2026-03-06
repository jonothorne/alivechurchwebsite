<?php
/**
 * iCal Calendar Parser
 * Fetches and parses events from Planning Center Online calendar feed
 */

class CalendarParser {
    private $calendarUrl;
    private $cacheFile;
    private $cacheLifetime = 3600; // 1 hour in seconds

    public function __construct($webcalUrl) {
        // Convert webcal:// to https://
        $this->calendarUrl = str_replace('webcal://', 'https://', $webcalUrl);
        $this->cacheFile = __DIR__ . '/../data/calendar-cache.json';
    }

    /**
     * Get events from cache or fetch fresh if needed
     */
    public function getEvents() {
        // Check if cache exists and is fresh
        if (file_exists($this->cacheFile)) {
            $cacheData = json_decode(file_get_contents($this->cacheFile), true);
            if ($cacheData && isset($cacheData['timestamp']) &&
                (time() - $cacheData['timestamp']) < $this->cacheLifetime) {
                return $cacheData['events'] ?? [];
            }
        }

        // Fetch fresh data
        $events = $this->fetchAndParseCalendar();

        // Cache the results
        $this->cacheEvents($events);

        return $events;
    }

    /**
     * Fetch and parse the iCal feed
     */
    private function fetchAndParseCalendar() {
        try {
            // Fetch the calendar data
            $icalData = @file_get_contents($this->calendarUrl);

            if ($icalData === false) {
                error_log('Failed to fetch calendar from: ' . $this->calendarUrl);
                return [];
            }

            // Parse the iCal data
            return $this->parseICalData($icalData);

        } catch (Exception $e) {
            error_log('Calendar parsing error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse iCal format into events array
     */
    private function parseICalData($icalData) {
        $events = [];
        $now = time();

        // Split into individual events
        $eventBlocks = explode('BEGIN:VEVENT', $icalData);
        array_shift($eventBlocks); // Remove header

        foreach ($eventBlocks as $eventBlock) {
            $event = $this->parseEvent($eventBlock);
            if ($event) {
                // Only include upcoming events (not ones that have already ended)
                $eventStart = strtotime($event['start_datetime']);
                $eventEnd = !empty($event['end_datetime']) ? strtotime($event['end_datetime']) : $eventStart;

                // Include if event hasn't ended yet (compare to current time, not just today)
                if ($eventEnd >= time()) {
                    $events[] = $event;
                }
            }
        }

        // Sort by date (upcoming first)
        usort($events, function($a, $b) {
            return strtotime($a['start_datetime']) - strtotime($b['start_datetime']);
        });

        // Group recurring events
        $events = $this->groupRecurringEvents($events);

        return $events;
    }

    /**
     * Detect and group recurring events
     */
    private function groupRecurringEvents($events) {
        $grouped = [];
        $processed = [];

        foreach ($events as $index => $event) {
            // Skip if already processed
            if (isset($processed[$index])) {
                continue;
            }

            // Find similar events (same title, similar time)
            $recurrences = [$event];
            $eventTime = date('H:i', strtotime($event['start_datetime']));

            for ($i = $index + 1; $i < count($events); $i++) {
                if (isset($processed[$i])) {
                    continue;
                }

                $compareEvent = $events[$i];
                $compareTime = date('H:i', strtotime($compareEvent['start_datetime']));

                // Same title and same time = likely recurring
                if ($event['title'] === $compareEvent['title'] && $eventTime === $compareTime) {
                    $recurrences[] = $compareEvent;
                    $processed[$i] = true;
                }
            }

            // If 3+ occurrences, it's recurring - group it
            if (count($recurrences) >= 3) {
                $frequency = $this->detectFrequency($recurrences);

                // Find the next future occurrence (not one that's already passed)
                $nextOccurrence = null;
                $now = time();
                foreach ($recurrences as $occurrence) {
                    $occurrenceEnd = !empty($occurrence['end_datetime'])
                        ? strtotime($occurrence['end_datetime'])
                        : strtotime($occurrence['start_datetime']);

                    if ($occurrenceEnd >= $now) {
                        $nextOccurrence = $occurrence['start_datetime'];
                        break;
                    }
                }

                // Only include if there's a future occurrence
                if ($nextOccurrence) {
                    $groupedEvent = $event;
                    $groupedEvent['is_recurring'] = true;
                    $groupedEvent['frequency'] = $frequency;
                    $groupedEvent['next_occurrence'] = $nextOccurrence;
                    $groupedEvent['start_datetime'] = $nextOccurrence; // Update to next occurrence
                    $groupedEvent['occurrences'] = $recurrences; // Keep all occurrences for filtering
                    $grouped[] = $groupedEvent;
                }
            } else {
                // One-time or infrequent event - only include future ones
                $now = time();
                foreach ($recurrences as $occurrence) {
                    $occurrenceEnd = !empty($occurrence['end_datetime'])
                        ? strtotime($occurrence['end_datetime'])
                        : strtotime($occurrence['start_datetime']);

                    if ($occurrenceEnd >= $now) {
                        $occurrence['is_recurring'] = false;
                        $grouped[] = $occurrence;
                    }
                }
            }

            $processed[$index] = true;
        }

        return $grouped;
    }

    /**
     * Detect frequency of recurring events
     */
    private function detectFrequency($occurrences) {
        if (count($occurrences) < 2) {
            return null;
        }

        // Check first few occurrences to determine pattern
        $intervals = [];
        for ($i = 0; $i < min(3, count($occurrences) - 1); $i++) {
            $first = strtotime($occurrences[$i]['start_datetime']);
            $second = strtotime($occurrences[$i + 1]['start_datetime']);
            $intervals[] = round(($second - $first) / 86400);
        }

        // Get average interval
        $avgInterval = (int) round(array_sum($intervals) / count($intervals));

        // Detect pattern based on average interval
        if ($avgInterval == 7) {
            $dayOfWeek = date('l', strtotime($occurrences[0]['start_datetime']));
            return 'Weekly on ' . $dayOfWeek;
        } elseif ($avgInterval >= 28 && $avgInterval <= 31) {
            return 'Monthly';
        } elseif ($avgInterval == 14) {
            $dayOfWeek = date('l', strtotime($occurrences[0]['start_datetime']));
            return 'Bi-weekly on ' . $dayOfWeek;
        } elseif ($avgInterval == 1) {
            return 'Daily';
        } elseif ($avgInterval >= 2 && $avgInterval <= 6) {
            // Multiple times per week
            return 'Regular event';
        } else {
            return 'Regular event';
        }
    }

    /**
     * Parse individual event from iCal block
     */
    private function parseEvent($eventBlock) {
        $lines = explode("\n", $eventBlock);
        $event = [
            'title' => '',
            'description' => '',
            'location' => '',
            'start_datetime' => '',
            'end_datetime' => '',
            'category' => 'weekly', // default
            'image' => '/assets/imgs/gallery/alive-church-worship-congregation.jpg', // default
        ];

        foreach ($lines as $line) {
            $line = trim($line);

            // Parse SUMMARY (title)
            if (strpos($line, 'SUMMARY:') === 0) {
                $event['title'] = $this->cleanValue(substr($line, 8));
            }

            // Parse DESCRIPTION
            elseif (strpos($line, 'DESCRIPTION:') === 0) {
                $event['description'] = $this->cleanValue(substr($line, 12));
            }

            // Parse LOCATION
            elseif (strpos($line, 'LOCATION:') === 0) {
                $event['location'] = $this->cleanValue(substr($line, 9));
            }

            // Parse DTSTART (start date/time)
            elseif (strpos($line, 'DTSTART') === 0) {
                $datetime = $this->parseDateTime($line);
                if ($datetime) {
                    $event['start_datetime'] = $datetime;
                }
            }

            // Parse DTEND (end date/time)
            elseif (strpos($line, 'DTEND') === 0) {
                $datetime = $this->parseDateTime($line);
                if ($datetime) {
                    $event['end_datetime'] = $datetime;
                }
            }

            // Parse CATEGORIES
            elseif (strpos($line, 'CATEGORIES:') === 0) {
                $category = strtolower($this->cleanValue(substr($line, 11)));
                if (in_array($category, ['weekly', 'special', 'youth', 'outreach'])) {
                    $event['category'] = $category;
                }
            }
        }

        // Only return events with required fields
        if (empty($event['title']) || empty($event['start_datetime'])) {
            return null;
        }

        // Format for display
        return $this->formatEventForDisplay($event);
    }

    /**
     * Parse iCal datetime format
     */
    private function parseDateTime($line) {
        // Extract datetime value (handles both DTSTART:value and DTSTART;params:value)
        if (preg_match('/:([\dTZ]+)/', $line, $matches)) {
            $dateStr = $matches[1];

            // Convert iCal format (YYYYMMDDTHHmmssZ) to standard format
            if (preg_match('/(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})/', $dateStr, $parts)) {
                return sprintf('%s-%s-%s %s:%s:%s',
                    $parts[1], $parts[2], $parts[3], // Date
                    $parts[4], $parts[5], $parts[6]  // Time
                );
            }

            // Handle date-only format (YYYYMMDD)
            if (preg_match('/(\d{4})(\d{2})(\d{2})/', $dateStr, $parts)) {
                return sprintf('%s-%s-%s 00:00:00', $parts[1], $parts[2], $parts[3]);
            }
        }

        return null;
    }

    /**
     * Clean iCal escaped values
     */
    private function cleanValue($value) {
        $value = str_replace(['\n', '\,', '\;', '\\\\'], ["\n", ',', ';', '\\'], $value);
        $value = trim($value);
        return $value;
    }

    /**
     * Format event for website display
     */
    private function formatEventForDisplay($event) {
        $startTime = strtotime($event['start_datetime']);
        $endTime = $event['end_datetime'] ? strtotime($event['end_datetime']) : null;

        // Format date
        $date = date('j F Y', $startTime); // e.g., "7 February 2026"

        // Format time
        $time = date('g:iA', $startTime);
        if ($endTime) {
            $time .= ' - ' . date('g:iA', $endTime);
        }

        // Determine if registration is required (based on keywords in title/description)
        $registrationRequired = false;
        $searchText = strtolower($event['title'] . ' ' . $event['description']);
        if (strpos($searchText, 'register') !== false ||
            strpos($searchText, 'ticket') !== false ||
            strpos($searchText, 'rsvp') !== false) {
            $registrationRequired = true;
        }

        // Auto-categorize if not set
        if ($event['category'] === 'weekly') {
            $titleLower = strtolower($event['title']);
            if (strpos($titleLower, 'youth') !== false) {
                $event['category'] = 'youth';
            } elseif (strpos($titleLower, 'community') !== false ||
                      strpos($titleLower, 'café') !== false ||
                      strpos($titleLower, 'cafe') !== false ||
                      strpos($titleLower, 'foodbank') !== false) {
                $event['category'] = 'outreach';
            } elseif (strpos($titleLower, 'conference') !== false ||
                      strpos($titleLower, 'special') !== false ||
                      strpos($titleLower, 'camp') !== false ||
                      strpos($titleLower, 'echo') !== false) {
                $event['category'] = 'special';
            } elseif (strpos($titleLower, 'group') !== false ||
                      strpos($titleLower, 'men\'s') !== false ||
                      strpos($titleLower, 'mens') !== false ||
                      strpos($titleLower, 'women\'s') !== false ||
                      strpos($titleLower, 'womens') !== false ||
                      strpos($titleLower, 'connect') !== false ||
                      strpos($titleLower, 'gateway') !== false) {
                $event['category'] = 'groups';
            }
        }

        // Create slug for registration
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $event['title']));
        $slug = trim($slug, '-');

        return [
            'title' => $event['title'],
            'category' => $event['category'],
            'date' => $date,
            'time' => $time,
            'location' => $event['location'] ?: 'Alive House, Norwich',
            'description' => $event['description'] ?: 'Join us for this event!',
            'image' => $event['image'],
            'cost' => 'Free', // Default - can be extracted from description if needed
            'registration_required' => $registrationRequired,
            'registration_url' => $registrationRequired ? '/events/register?event=' . $slug : null,
            'info_url' => '/events',
            'slug' => $slug,
            'start_datetime' => $event['start_datetime'],
            'is_recurring' => $event['is_recurring'] ?? false,
            'frequency' => $event['frequency'] ?? null,
            'next_occurrence' => $event['next_occurrence'] ?? null,
            'occurrences' => $event['occurrences'] ?? [],
        ];
    }

    /**
     * Cache events to file
     */
    private function cacheEvents($events) {
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheData = [
            'timestamp' => time(),
            'events' => $events
        ];

        file_put_contents($this->cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
    }

    /**
     * Force refresh cache
     */
    public function refreshCache() {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        return $this->getEvents();
    }
}
