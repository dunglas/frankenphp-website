# FrankenPHP Documentation

This repository contains the source code powering [frankenphp.dev](https://frankenphp.dev). The site is built using [Hugo](https://gohugo.io/), a static site generator, and styled with [Tailwind CSS](https://tailwindcss.com/).

## Getting Started

To work on this project and update the Tailwind CSS locally, follow these steps:

### 1. Clone the Repository

First, clone this repository to your local machine and init the project:

```bash
git clone git@github.com:dunglas/frankenphp-website.git
cd frankenphp-website
pnpm install
```

### 2. Clone the Documentation

Before building the project, you need to fetch the documentation content. Run the following command to clone the documentation:

```php
php clone-documentation.php
```

Please note that you might need to set up a GitHub token with the necessary permissions as a GITHUB_KEY environment variable.

### 3. Update Tailwind CSS Locally

To watch and update the Tailwind CSS during development, use the following command:

```bash
pnpm watch
```

### 4. Run the project locally

Once you have cloned the documentation and updated the CSS, you can start the development server using Hugo. Open a new terminal tab or window and run the following command:

```bash
hugo server --disableFastRender
```

This will launch a local server, and you can access the FrankenPHP Documentation site at http://localhost:1313.

## Prettier Integration

Prettier is configured in this project to help you maintain consistent and well-formatted templates. To format your Hugo templates, use the following command:

```bash
pnpm prettify
```