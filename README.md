# DevTalk Forum

## Objective
This small discussion forum, built with **PHP + SQLite + Bootstrap 5**, is the 
target application for **Lab 4** and **Lab 5**. For Lab 4, you will to find and 
document the security vulnerabilities in this application. For Lab 5, you will 
patch the vulnerbilities and confirm your attacks no longer work.


## Requirements

- PHP 8.1 or newer (with the `pdo_sqlite` extension — bundled with most PHP builds)
- [Composer](https://getcomposer.org/)

Check your PHP version and extension:

```bash
php -v
php -m | grep -i sqlite
```

## Setup

From the `forum` directory:

```bash
# 1. Build and seed the database
composer reset-db

# 2. Start the development server
composer start
```

Then open <http://localhost:8000> in your browser.

To wipe any changes and start fresh at any time, run `composer reset-db` again.

## Sample accounts

The seeded database includes these accounts (username / password):

| Username | Password       | Role  |
|----------|----------------|-------|
| admin    | `Sup3rSecret!` | admin |
| alice    | `password123`  | user  |
| bob      | `hunter2`      | user  |
| carol    | `letmein2024`  | user  |

You can also register your own account from the **Register** page.

## Features

- Public homepage listing forum topics, newest first
- Read topics and comments without logging in
- Register and log in
- Create topics (title + body; HTML and images are supported)
- Edit and delete topics
- Comment on topics and delete comments
- User profiles with a list of their topics
- Topic search
- Image preview for topics that reference an image URL

## Project layout

```
forum/
├── composer.json          # defines `composer start` and `composer reset-db`
├── public/                # web root (point your server here)
│   ├── index.php          # homepage
│   ├── topic.php          # view a topic + comments
│   ├── login.php / register.php / logout.php
│   ├── create_topic.php / edit_topic.php / delete_topic.php
│   ├── add_comment.php / delete_comment.php
│   ├── search.php         # topic search
│   ├── profile.php        # user profile
│   ├── fetch_image.php    # server-side image preview
│   └── assets/            # CSS and images
├── src/                   # config, DB connection, shared helpers
└── database/              # schema + build/seed script (SQLite file is generated)
```

## Troubleshooting

- **`php: command not found`** — install PHP (e.g. `brew install php` on macOS).
- **Database errors on first load** — run `composer reset-db` before `composer start`.
- **Port 8000 in use** — edit the `start` script in `composer.json` to use another port.
