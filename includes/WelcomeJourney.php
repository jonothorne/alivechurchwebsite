<?php
/**
 * Welcome Journey - Automated Email Sequences
 *
 * Manages the welcome email sequence for new visitors:
 * - Day 0: "We're excited to meet you!" (immediate)
 * - Day 1 after visit: "How was your first visit?"
 * - Week 2: "Join a group" invitation
 * - Week 4: "Serve with us" invitation
 */

class WelcomeJourney
{
    private PDO $pdo;
    private array $site;

    // Email sequence configuration (days after registration)
    private const SEQUENCE = [
        'welcome' => [
            'delay_days' => 0,
            'subject' => "We're excited to meet you!",
            'template' => 'welcome',
            'trigger' => 'registration' // Sent immediately on registration
        ],
        'post_visit' => [
            'delay_days' => 1,
            'subject' => 'How was your first visit?',
            'template' => 'post_visit',
            'trigger' => 'visit' // Sent 1 day after their visit (next Sunday)
        ],
        'join_group' => [
            'delay_days' => 14,
            'subject' => 'Life is better together',
            'template' => 'join_group',
            'trigger' => 'visit' // Sent 2 weeks after visit
        ],
        'serve' => [
            'delay_days' => 28,
            'subject' => 'Ready to make a difference?',
            'template' => 'serve',
            'trigger' => 'visit' // Sent 4 weeks after visit
        ]
    ];

    public function __construct(PDO $pdo, array $site = [])
    {
        $this->pdo = $pdo;
        $this->site = $site ?: $this->loadSiteConfig();
    }

    /**
     * Load site configuration if not provided
     */
    private function loadSiteConfig(): array
    {
        return [
            'name' => getenv('CHURCH_NAME') ?: 'Alive Church',
            'email' => getenv('CHURCH_ADMIN_EMAIL') ?: 'admin@alivechurch.co.uk',
            'phone' => '01onal',
            'location' => 'Nelson Street, Norwich NR2 4DR',
            'maps_url' => 'https://maps.google.com/?q=Alive+Church+Norwich',
            'service_times' => 'Sundays at 11:00 AM'
        ];
    }

    /**
     * Start a welcome journey for a new visitor registration
     */
    public function startJourney(int $formSubmissionId, array $visitorData): bool
    {
        try {
            // Create welcome journey record
            $stmt = $this->pdo->prepare("
                INSERT INTO welcome_journeys
                (form_submission_id, visitor_name, visitor_email, status, registered_at)
                VALUES (?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $formSubmissionId > 0 ? $formSubmissionId : null, // Use NULL if 0
                $visitorData['name'] ?? '',
                $visitorData['email'] ?? ''
            ]);

            $journeyId = $this->pdo->lastInsertId();

            // Queue immediate welcome email
            $this->queueEmail($journeyId, 'welcome', new DateTime());

            // Calculate expected visit date (next Sunday after registration)
            $expectedVisitDate = $this->getNextSunday();

            // Update journey with expected visit date
            $stmt = $this->pdo->prepare("
                UPDATE welcome_journeys
                SET expected_visit_date = ?
                WHERE id = ?
            ");
            $stmt->execute([$expectedVisitDate->format('Y-m-d'), $journeyId]);

            // Queue post-visit emails based on expected visit date
            $this->queuePostVisitEmails($journeyId, $expectedVisitDate);

            return true;
        } catch (PDOException $e) {
            error_log("WelcomeJourney::startJourney error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue all post-visit emails
     */
    private function queuePostVisitEmails(int $journeyId, DateTime $visitDate): void
    {
        foreach (self::SEQUENCE as $emailType => $config) {
            if ($config['trigger'] === 'visit') {
                $sendDate = clone $visitDate;
                $sendDate->modify("+{$config['delay_days']} days");
                $this->queueEmail($journeyId, $emailType, $sendDate);
            }
        }
    }

    /**
     * Queue a single email for sending
     */
    private function queueEmail(int $journeyId, string $emailType, DateTime $sendAt): void
    {
        $config = self::SEQUENCE[$emailType] ?? null;
        if (!$config) return;

        $stmt = $this->pdo->prepare("
            INSERT INTO welcome_journey_emails
            (journey_id, email_type, subject, scheduled_at, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $journeyId,
            $emailType,
            $config['subject'],
            $sendAt->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get the next Sunday from today
     */
    private function getNextSunday(): DateTime
    {
        $today = new DateTime();
        $dayOfWeek = (int)$today->format('w'); // 0 = Sunday

        if ($dayOfWeek === 0) {
            // If today is Sunday, return next Sunday
            $today->modify('+7 days');
        } else {
            // Calculate days until Sunday
            $daysUntilSunday = 7 - $dayOfWeek;
            $today->modify("+{$daysUntilSunday} days");
        }

        // Set time to noon (after service)
        $today->setTime(12, 0, 0);

        return $today;
    }

    /**
     * Process pending emails that are due to be sent
     */
    public function processQueue(int $limit = 50): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        // Get pending emails that are due
        $stmt = $this->pdo->prepare("
            SELECT e.*, j.visitor_name, j.visitor_email, j.status as journey_status
            FROM welcome_journey_emails e
            JOIN welcome_journeys j ON e.journey_id = j.id
            WHERE e.status = 'pending'
              AND e.scheduled_at <= NOW()
              AND j.status IN ('active', 'visited')
            ORDER BY e.scheduled_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($emails as $email) {
            // Skip if journey was cancelled or unsubscribed
            if ($email['journey_status'] === 'cancelled' || $email['journey_status'] === 'unsubscribed') {
                $this->updateEmailStatus($email['id'], 'skipped');
                $results['skipped']++;
                continue;
            }

            // Send the email
            $sent = $this->sendEmail($email);

            if ($sent) {
                $this->updateEmailStatus($email['id'], 'sent');
                $results['sent']++;
            } else {
                $this->updateEmailStatus($email['id'], 'failed');
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Update email status
     */
    private function updateEmailStatus(int $emailId, string $status): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE welcome_journey_emails
            SET status = ?, sent_at = CASE WHEN ? = 'sent' THEN NOW() ELSE NULL END
            WHERE id = ?
        ");
        $stmt->execute([$status, $status, $emailId]);
    }

    /**
     * Send a single email
     */
    private function sendEmail(array $email): bool
    {
        $template = $this->getEmailTemplate($email['email_type'], [
            'name' => $email['visitor_name'],
            'email' => $email['visitor_email'],
            'journey_id' => $email['journey_id']
        ]);

        if (!$template) {
            error_log("WelcomeJourney: No template found for {$email['email_type']}");
            return false;
        }

        $to = $email['visitor_email'];
        $subject = $email['subject'];

        // Generate unsubscribe link
        $unsubscribeToken = $this->generateUnsubscribeToken($email['journey_id']);
        $unsubscribeUrl = $this->getBaseUrl() . '/unsubscribe?token=' . $unsubscribeToken;

        // Add unsubscribe link to template
        $body = str_replace('{{unsubscribe_url}}', $unsubscribeUrl, $template);

        // Email headers
        $fromName = $this->site['name'];
        $fromEmail = getenv('MAIL_FROM_ADDRESS') ?: 'noreply@alivechurch.co.uk';

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: {$this->site['email']}",
            'X-Mailer: PHP/' . phpversion(),
            "List-Unsubscribe: <{$unsubscribeUrl}>"
        ];

        // Send email
        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));

        if (!$sent) {
            error_log("WelcomeJourney: Failed to send email to {$to}");
        }

        return $sent;
    }

    /**
     * Generate unsubscribe token
     */
    private function generateUnsubscribeToken(int $journeyId): string
    {
        $token = bin2hex(random_bytes(16));

        // Store token
        $stmt = $this->pdo->prepare("
            UPDATE welcome_journeys
            SET unsubscribe_token = ?
            WHERE id = ?
        ");
        $stmt->execute([$token, $journeyId]);

        return $token;
    }

    /**
     * Handle unsubscribe request
     */
    public function unsubscribe(string $token): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE welcome_journeys
            SET status = 'unsubscribed'
            WHERE unsubscribe_token = ?
        ");
        $stmt->execute([$token]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Mark a journey as visited (visitor attended church)
     */
    public function markAsVisited(int $journeyId, ?DateTime $visitDate = null): bool
    {
        $visitDate = $visitDate ?? new DateTime();

        $stmt = $this->pdo->prepare("
            UPDATE welcome_journeys
            SET status = 'visited', actual_visit_date = ?
            WHERE id = ?
        ");
        $stmt->execute([$visitDate->format('Y-m-d'), $journeyId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Cancel a journey (stop all future emails)
     */
    public function cancelJourney(int $journeyId): bool
    {
        // Update journey status
        $stmt = $this->pdo->prepare("
            UPDATE welcome_journeys SET status = 'cancelled' WHERE id = ?
        ");
        $stmt->execute([$journeyId]);

        // Cancel pending emails
        $stmt = $this->pdo->prepare("
            UPDATE welcome_journey_emails
            SET status = 'cancelled'
            WHERE journey_id = ? AND status = 'pending'
        ");
        $stmt->execute([$journeyId]);

        return true;
    }

    /**
     * Get journey statistics
     */
    public function getStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) as total_journeys,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'visited' THEN 1 ELSE 0 END) as visited,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM welcome_journeys
        ");
        $journeyStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) as total_emails,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM welcome_journey_emails
        ");
        $emailStats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'journeys' => $journeyStats,
            'emails' => $emailStats
        ];
    }

    /**
     * Get all journeys with optional filtering
     */
    public function getJourneys(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'j.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(j.visitor_name LIKE ? OR j.visitor_email LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $this->pdo->prepare("
            SELECT j.*,
                   (SELECT COUNT(*) FROM welcome_journey_emails WHERE journey_id = j.id AND status = 'sent') as emails_sent,
                   (SELECT COUNT(*) FROM welcome_journey_emails WHERE journey_id = j.id AND status = 'pending') as emails_pending
            FROM welcome_journeys j
            WHERE {$whereClause}
            ORDER BY j.registered_at DESC
            LIMIT ? OFFSET ?
        ");
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get email template
     */
    private function getEmailTemplate(string $type, array $data): ?string
    {
        $templateFile = __DIR__ . "/../templates/emails/welcome-journey/{$type}.php";

        if (!file_exists($templateFile)) {
            error_log("WelcomeJourney: Template not found: {$templateFile}");
            return null;
        }

        // Extract variables for template
        $name = $data['name'] ?? 'Friend';
        $firstName = explode(' ', $name)[0];
        $email = $data['email'] ?? '';
        $site = $this->site;
        $journeyId = $data['journey_id'] ?? 0;

        ob_start();
        include $templateFile;
        return ob_get_clean();
    }

    /**
     * Get base URL for links
     */
    private function getBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'alivechur.ch';
        return "{$protocol}://{$host}";
    }

    /**
     * Complete journey (all emails sent)
     */
    public function checkAndCompleteJourneys(): int
    {
        // Find journeys where all emails are sent
        $stmt = $this->pdo->prepare("
            UPDATE welcome_journeys j
            SET status = 'completed'
            WHERE status IN ('active', 'visited')
              AND NOT EXISTS (
                  SELECT 1 FROM welcome_journey_emails e
                  WHERE e.journey_id = j.id AND e.status = 'pending'
              )
        ");
        $stmt->execute();

        return $stmt->rowCount();
    }
}
