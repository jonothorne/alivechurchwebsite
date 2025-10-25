import { defineType, defineField } from "sanity";

export default defineType({
  name: "visionContent",
  title: "Vision & Mission Content",
  type: "document",
  fields: [
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
      name: "visionStatement",
      title: "Vision Statement",
      type: "text",
      rows: 3,
      initialValue: "To see community-wide transformation",
    }),
    defineField({
      name: "visionDescription",
      title: "Vision Description",
      type: "array",
      of: [{ type: "block" }],
      description: "Detailed explanation of the vision",
    }),
    defineField({
      name: "missionStatement",
      title: "Mission Statement",
      type: "text",
      rows: 3,
      initialValue: "To be restorers of the breach",
    }),
    defineField({
      name: "missionDescription",
      title: "Mission Description",
      type: "array",
      of: [{ type: "block" }],
      description: "Detailed explanation of the mission",
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
          ],
        },
      ],
    }),
  ],
  preview: {
    prepare() {
      return {
        title: "Vision & Mission Content",
        subtitle: "Edit vision and mission page",
      };
    },
  },
});
