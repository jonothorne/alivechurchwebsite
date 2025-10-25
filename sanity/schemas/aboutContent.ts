import { defineType, defineField } from "sanity";

export default defineType({
  name: "aboutContent",
  title: "About Page Content",
  type: "document",
  fields: [
    defineField({
      name: "heroTitle",
      title: "Hero Title",
      type: "string",
      initialValue: "About Alive Church",
    }),
    defineField({
      name: "heroSubtitle",
      title: "Hero Subtitle",
      type: "string",
      initialValue: "Our Story",
    }),
    defineField({
      name: "heroDescription",
      title: "Hero Description",
      type: "text",
      rows: 2,
      initialValue:
        "For 40 years, we've been a community of faith, hope, and love in the heart of Norwich.",
    }),
    defineField({
      name: "heroImage",
      title: "Hero Background Image",
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
      name: "storyContent",
      title: "Our Story Content",
      type: "array",
      of: [
        {
          type: "block",
        },
      ],
      description: "Main story text (supports rich text formatting)",
    }),
    defineField({
      name: "coreValues",
      title: "Core Values",
      type: "array",
      of: [
        {
          type: "object",
          fields: [
            {
              name: "title",
              title: "Value Title",
              type: "string",
            },
            {
              name: "description",
              title: "Description",
              type: "text",
              rows: 3,
            },
            {
              name: "icon",
              title: "Icon Name",
              type: "string",
              description: "Icon identifier (church, heart, users2, etc.)",
            },
          ],
        },
      ],
    }),
  ],
  preview: {
    prepare() {
      return {
        title: "About Page Content",
        subtitle: "Edit about page text and images",
      };
    },
  },
});
