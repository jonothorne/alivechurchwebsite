import { StructureBuilder } from "sanity/structure";

export const structure = (S: StructureBuilder) =>
  S.list()
    .title("Content")
    .items([
      // Pages Section
      S.listItem()
        .title("Pages")
        .child(
          S.list()
            .title("Pages")
            .items([
              // Homepage
              S.listItem()
                .title("Homepage")
                .child(
                  S.document()
                    .schemaType("homepageContent")
                    .documentId("homepageContent")
                ),
              // About Page
              S.listItem()
                .title("About Page")
                .child(
                  S.document()
                    .schemaType("aboutContent")
                    .documentId("aboutContent")
                ),
              // Vision & Mission Page
              S.listItem()
                .title("Vision & Mission")
                .child(
                  S.document()
                    .schemaType("visionContent")
                    .documentId("visionContent")
                ),
              // Other Generic Pages
              S.listItem()
                .title("Other Pages")
                .child(S.documentTypeList("page").title("Other Pages")),
            ])
        ),

      // Divider
      S.divider(),

      // Content Section
      S.listItem()
        .title("Blog & News")
        .child(S.documentTypeList("post").title("Blog Posts")),

      S.listItem()
        .title("Events")
        .child(S.documentTypeList("event").title("Events")),

      // Divider
      S.divider(),

      // People Section
      S.listItem()
        .title("Team & Leadership")
        .child(S.documentTypeList("teamMember").title("Team Members")),

      // Divider
      S.divider(),

      // Settings Section
      S.listItem()
        .title("Site Settings")
        .child(
          S.document()
            .schemaType("siteSettings")
            .documentId("siteSettings")
        ),
    ]);
