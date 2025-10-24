import { defineType, defineField } from "sanity";

export default defineType({
  name: "siteSettings",
  title: "Site Settings",
  type: "document",
  fields: [
    defineField({
      name: "title",
      title: "Site Title",
      type: "string",
      initialValue: "Alive Church Norwich",
    }),
    defineField({
      name: "description",
      title: "Site Description",
      type: "text",
      rows: 3,
      initialValue:
        "Alive Church is a modern pentecostal church in Norwich with our core roots in community and family.",
    }),
    defineField({
      name: "logo",
      title: "Logo",
      type: "image",
      options: {
        hotspot: true,
      },
    }),
    defineField({
      name: "serviceTime",
      title: "Service Time",
      type: "string",
      initialValue: "Sunday at 11am",
    }),
    defineField({
      name: "address",
      title: "Address",
      type: "object",
      fields: [
        {
          name: "street",
          title: "Street Address",
          type: "string",
          initialValue: "Alive House, Nelson Street",
        },
        {
          name: "city",
          title: "City",
          type: "string",
          initialValue: "Norwich",
        },
        {
          name: "postcode",
          title: "Postcode",
          type: "string",
          initialValue: "NR2 4DR",
        },
      ],
    }),
    defineField({
      name: "contact",
      title: "Contact Information",
      type: "object",
      fields: [
        {
          name: "email",
          title: "Email",
          type: "string",
          initialValue: "office@alive.me.uk",
        },
        {
          name: "phone",
          title: "Phone",
          type: "string",
        },
      ],
    }),
    defineField({
      name: "social",
      title: "Social Media",
      type: "object",
      fields: [
        {
          name: "facebook",
          title: "Facebook URL",
          type: "url",
        },
        {
          name: "instagram",
          title: "Instagram URL",
          type: "url",
        },
        {
          name: "twitter",
          title: "Twitter/X URL",
          type: "url",
        },
        {
          name: "youtube",
          title: "YouTube URL",
          type: "url",
        },
      ],
    }),
    defineField({
      name: "vision",
      title: "Vision Statement",
      type: "text",
      rows: 3,
      initialValue: "To see community-wide transformation",
    }),
    defineField({
      name: "mission",
      title: "Mission Statement",
      type: "text",
      rows: 3,
      initialValue: "To be restorers of the breach",
    }),
  ],
  preview: {
    prepare() {
      return {
        title: "Site Settings",
      };
    },
  },
});
