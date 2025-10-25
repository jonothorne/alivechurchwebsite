import page from "./page";
import post from "./post";
import event from "./event";
import teamMember from "./teamMember";
import siteSettings from "./siteSettings";
import homepageContent from "./homepageContent";
import aboutContent from "./aboutContent";
import visionContent from "./visionContent";

export const schemaTypes = [
  // Page Content
  homepageContent,
  aboutContent,
  visionContent,

  // Dynamic Content
  page,
  post,
  event,
  teamMember,

  // Settings
  siteSettings,
];
