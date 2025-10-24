# Alive Church Website

Modern, vibrant website for Alive Church Norwich - a pentecostal church rooted in community and family.

## Features

- 🎨 **Modern Design** - Clean, contemporary design with Alive Church branding (#eb008b primary color)
- 📱 **Fully Responsive** - Works beautifully on desktop, tablet, and mobile devices
- 🎛️ **Full CMS** - Sanity CMS for complete content management (no code required!)
- ⚡ **Fast Performance** - Built with Next.js 14 for optimal speed and SEO
- 🎯 **Key Pages** - Home, About, Vision, Leadership, Events, Connect, Give, News/Blog

## Tech Stack

- **Framework**: Next.js 14 (App Router)
- **Language**: TypeScript
- **Styling**: Tailwind CSS
- **CMS**: Sanity CMS
- **Animations**: Framer Motion
- **Icons**: Lucide React
- **Forms**: React Hook Form

## Getting Started

### Prerequisites

- Node.js 18+ installed
- A Sanity account (free at https://www.sanity.io/)

### Installation

1. **Install Dependencies**

```bash
npm install
```

2. **Set up Environment Variables**

Copy `.env.local.example` to `.env.local` and add your Sanity credentials:

```bash
cp .env.local.example .env.local
```

Then edit `.env.local` with your Sanity project details (see "Sanity Setup" below).

3. **Run Development Server**

```bash
npm run dev
```

Visit [http://localhost:3000](http://localhost:3000) to see your website!

4. **Access CMS Studio**

Visit [http://localhost:3000/studio](http://localhost:3000/studio) to manage content.

## Sanity CMS Setup

### 1. Create a Sanity Account

1. Go to [https://www.sanity.io/](https://www.sanity.io/)
2. Sign up for a free account
3. Create a new project
4. Choose "Start from scratch"

### 2. Get Your Project Details

After creating your project:

1. Go to your project settings at https://www.sanity.io/manage
2. Copy your **Project ID**
3. Create a new dataset called "production"
4. Go to API settings and create a token with "Editor" permissions
5. Copy the token

### 3. Update Environment Variables

Edit `.env.local`:

```env
NEXT_PUBLIC_SANITY_PROJECT_ID="your_project_id_here"
NEXT_PUBLIC_SANITY_DATASET="production"
NEXT_PUBLIC_SANITY_API_VERSION="2024-01-01"
SANITY_API_TOKEN="your_token_here"
```

### 4. Deploy Sanity Schemas

Once you've added your credentials, visit `/studio` in your browser. Sanity will automatically deploy the schemas.

## Managing Content with CMS

### Accessing the CMS

1. Run your dev server: `npm run dev`
2. Visit `http://localhost:3000/studio`
3. Log in with your Sanity credentials

### What You Can Manage

- **Pages** - Create and edit any page on the site
- **Blog Posts** - Write articles with rich text, images, and categories
- **Events** - Add events with dates, times, locations, and registration links
- **Team Members** - Add leadership and staff with photos and bios
- **Site Settings** - Update contact info, social links, service times globally

### Adding Content

#### Creating a Blog Post

1. Go to `/studio`
2. Click "Blog Post" in the sidebar
3. Click "Create new"
4. Fill in title, content, featured image, etc.
5. Click "Publish"

#### Adding an Event

1. Go to `/studio`
2. Click "Event"
3. Fill in event details (title, date, location)
4. Add event image and description
5. Optionally add registration URL
6. Click "Publish"

#### Managing Team Members

1. Go to `/studio`
2. Click "Team Member"
3. Add name, role, photo, and bio
4. Set display order
5. Mark as "Senior Leadership" if applicable
6. Click "Publish"

## Available Scripts

```bash
npm run dev          # Start development server
npm run build        # Build for production
npm run start        # Start production server
npm run lint         # Run ESLint
```

## Deployment

### Recommended: Vercel

1. Push your code to GitHub
2. Visit [vercel.com](https://vercel.com)
3. Import your GitHub repository
4. Add environment variables from `.env.local`
5. Deploy!

Vercel will automatically deploy on every push to main.

### Environment Variables for Production

Make sure to add these in your hosting platform:

- `NEXT_PUBLIC_SANITY_PROJECT_ID`
- `NEXT_PUBLIC_SANITY_DATASET`
- `NEXT_PUBLIC_SANITY_API_VERSION`
- `SANITY_API_TOKEN`

## Customization

### Changing Colors

Edit `tailwind.config.ts`:

```ts
colors: {
  primary: "#eb008b",        // Main brand color
  "primary-dark": "#c2006f",  // Darker variant
  "primary-light": "#ff1a9f", // Lighter variant
},
```

### Adding New Pages

1. Create a new folder in `app/` (e.g., `app/newpage`)
2. Add a `page.tsx` file
3. The page will automatically be available at `/newpage`

## Support

For questions or issues:

- Email: office@alive.me.uk
- Check the code comments for guidance

## License

Private - Alive Church Norwich

---

Built with ❤️ for Alive Church by Claude Code
