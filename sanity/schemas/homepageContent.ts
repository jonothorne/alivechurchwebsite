import { defineType, defineField } from "sanity";

export default defineType({
  name: "homepageContent",
  title: "Homepage Content",
  type: "document",
  fields: [
    defineField({
      name: "heroTitle",
      title: "Hero Title",
      type: "string",
      description: "Main headline on the homepage",
      initialValue: "Welcome to Alive Church",
    }),
    defineField({
      name: "heroSubtitle",
      title: "Hero Subtitle",
      type: "string",
      initialValue: "Norwich",
    }),
    defineField({
      name: "heroDescription",
      title: "Hero Description",
      type: "text",
      rows: 3,
      initialValue:
        "A modern pentecostal church rooted in community and family. Join us as we pursue transformation and restoration together.",
    }),
    defineField({
      name: "heroImage",
      title: "Hero Background Image",
      type: "image",
      description: "Large background image for the hero section",
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
      name: "heroCtaText",
      title: "Hero Button Text",
      type: "string",
      initialValue: "Plan Your Visit",
    }),
    defineField({
      name: "heroCtaLink",
      title: "Hero Button Link",
      type: "string",
      initialValue: "/about",
    }),
    defineField({
      name: "communityGalleryTitle",
      title: "Community Gallery Title",
      type: "string",
      initialValue: "Our Church Family",
    }),
    defineField({
      name: "communityGalleryDescription",
      title: "Community Gallery Description",
      type: "text",
      rows: 2,
    }),
    defineField({
      name: "communityImages",
      title: "Community Gallery Images",
      type: "array",
      of: [
        {
          type: "object",
          fields: [
            {
              name: "image",
              title: "Image",
              type: "image",
              options: { hotspot: true },
              fields: [
                {
                  name: "alt",
                  type: "string",
                  title: "Alternative text",
                },
              ],
            },
            {
              name: "caption",
              title: "Caption",
              type: "string",
            },
          ],
        },
      ],
      validation: (Rule) => Rule.max(4),
    }),
    defineField({
      name: "visionText",
      title: "Vision Statement (Homepage)",
      type: "text",
      rows: 2,
      initialValue:
        "To see community-wide transformation through the power of God's love.",
    }),
    defineField({
      name: "missionText",
      title: "Mission Statement (Homepage)",
      type: "text",
      rows: 2,
      initialValue: "To be restorers of the breach, rebuilding lives and communities.",
    }),
  ],
  preview: {
    prepare() {
      return {
        title: "Homepage Content",
        subtitle: "Edit homepage text, images, and layout",
      };
    },
  },
});
