import { client } from "./client";

// Homepage Content
export async function getHomepageContent() {
  return client.fetch(
    `*[_type == "homepageContent"][0]{
      heroTitle,
      heroSubtitle,
      heroDescription,
      "heroImage": heroImage.asset->url,
      heroCtaText,
      heroCtaLink,
      communityGalleryTitle,
      communityGalleryDescription,
      communityImages[]{
        "image": image.asset->url,
        caption
      },
      visionText,
      missionText
    }`
  );
}

// About Page Content
export async function getAboutContent() {
  return client.fetch(
    `*[_type == "aboutContent"][0]{
      heroTitle,
      heroSubtitle,
      heroDescription,
      "heroImage": heroImage.asset->url,
      storyContent,
      coreValues[]{
        title,
        description,
        icon
      },
      whatToExpect[]{
        title,
        description
      }
    }`
  );
}

// Vision Page Content
export async function getVisionContent() {
  return client.fetch(
    `*[_type == "visionContent"][0]{
      "heroImage": heroImage.asset->url,
      visionStatement,
      visionDescription,
      missionStatement,
      missionDescription,
      coreValues[]{
        title,
        description
      }
    }`
  );
}

// Get all events
export async function getEvents() {
  return client.fetch(
    `*[_type == "event"] | order(startDate asc){
      _id,
      title,
      slug,
      description,
      "image": image.asset->url,
      startDate,
      endDate,
      location,
      category,
      registrationRequired,
      registrationUrl
    }`
  );
}

// Get all blog posts
export async function getPosts(limit = 10) {
  return client.fetch(
    `*[_type == "post"] | order(publishedAt desc)[0...${limit}]{
      _id,
      title,
      slug,
      excerpt,
      "featuredImage": featuredImage.asset->url,
      "author": author->name,
      publishedAt,
      categories
    }`
  );
}

// Get team members
export async function getTeamMembers() {
  return client.fetch(
    `*[_type == "teamMember"] | order(order asc){
      _id,
      name,
      role,
      "photo": photo.asset->url,
      bio,
      email,
      featured
    }`
  );
}

// Get site settings
export async function getSiteSettings() {
  return client.fetch(
    `*[_type == "siteSettings"][0]{
      title,
      description,
      "logo": logo.asset->url,
      serviceTime,
      address{
        street,
        city,
        postcode
      },
      contact{
        email,
        phone
      },
      social{
        facebook,
        instagram,
        twitter,
        youtube
      },
      vision,
      mission
    }`
  );
}
