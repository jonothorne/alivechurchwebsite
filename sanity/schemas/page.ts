import { defineType, defineField } from "sanity";

export default defineType({
  name: "page",
  title: "Page",
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
      name: "parent",
      title: "Parent Page",
      type: "reference",
      to: [{ type: "page" }],
      description: "Optional: Select a parent page to create a sub-page",
    }),
    defineField({
      name: "description",
      title: "Description",
      type: "text",
      description: "SEO description for this page",
      validation: (Rule) => Rule.max(160),
    }),
    defineField({
      name: "content",
      title: "Content",
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
      name: "showInNavigation",
      title: "Show in Navigation",
      type: "boolean",
      initialValue: true,
    }),
    defineField({
      name: "order",
      title: "Order",
      type: "number",
      description: "Order in navigation menu (lower numbers appear first)",
    }),
  ],
  preview: {
    select: {
      title: "title",
      parent: "parent.title",
    },
    prepare({ title, parent }) {
      return {
        title: parent ? `${parent} > ${title}` : title,
      };
    },
  },
});
