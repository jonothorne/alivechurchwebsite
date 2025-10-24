import { defineType, defineField } from "sanity";

export default defineType({
  name: "event",
  title: "Event",
  type: "document",
  fields: [
    defineField({
      name: "title",
      title: "Title",
      type: "string",
      validation: (Rule) => Rule.required(),
    }),
    defineField({
      name: "slug",
      title: "Slug",
      type: "slug",
      options: {
        source: "title",
        maxLength: 96,
      },
      validation: (Rule) => Rule.required(),
    }),
    defineField({
      name: "description",
      title: "Description",
      type: "text",
      rows: 3,
    }),
    defineField({
      name: "image",
      title: "Event Image",
      type: "image",
      options: {
        hotspot: true,
      },
      fields: [
        {
          name: "alt",
          type: "string",
          title: "Alternative text",
        },
      ],
    }),
    defineField({
      name: "content",
      title: "Full Content",
      type: "array",
      of: [
        {
          type: "block",
        },
        {
          type: "image",
          fields: [
            {
              name: "alt",
              type: "string",
              title: "Alternative text",
            },
          ],
        },
      ],
    }),
    defineField({
      name: "startDate",
      title: "Start Date & Time",
      type: "datetime",
      validation: (Rule) => Rule.required(),
    }),
    defineField({
      name: "endDate",
      title: "End Date & Time",
      type: "datetime",
    }),
    defineField({
      name: "location",
      title: "Location",
      type: "string",
      initialValue: "Alive House, Nelson Street, Norwich NR2 4DR",
    }),
    defineField({
      name: "category",
      title: "Category",
      type: "string",
      options: {
        list: [
          { title: "Sunday Service", value: "service" },
          { title: "Special Event", value: "special" },
          { title: "Small Group", value: "small-group" },
          { title: "Youth", value: "youth" },
          { title: "Kids", value: "kids" },
          { title: "Community", value: "community" },
          { title: "Conference", value: "conference" },
        ],
      },
    }),
    defineField({
      name: "registrationRequired",
      title: "Registration Required",
      type: "boolean",
      initialValue: false,
    }),
    defineField({
      name: "registrationUrl",
      title: "Registration URL",
      type: "url",
      hidden: ({ document }) => !document?.registrationRequired,
    }),
    defineField({
      name: "featured",
      title: "Featured Event",
      type: "boolean",
      description: "Show this event prominently on the homepage",
      initialValue: false,
    }),
  ],
  preview: {
    select: {
      title: "title",
      startDate: "startDate",
      media: "image",
    },
    prepare({ title, startDate, media }) {
      const date = startDate ? new Date(startDate).toLocaleDateString() : "";
      return {
        title,
        subtitle: date,
        media,
      };
    },
  },
  orderings: [
    {
      title: "Start Date, New",
      name: "startDateDesc",
      by: [{ field: "startDate", direction: "desc" }],
    },
    {
      title: "Start Date, Old",
      name: "startDateAsc",
      by: [{ field: "startDate", direction: "asc" }],
    },
  ],
});
