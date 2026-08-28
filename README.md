# Abide N Me

**Abide N Me** is a Scripture reading and devotional app built around families, churches, small groups, pastors, and ministry leaders who want to create their own guided Bible reading plans and walk through them together.

The project began as **Feed My Sheep** and was created to solve a simple limitation: popular Bible apps provide many prebuilt reading plans and devotionals, but generally do not allow ordinary parents, pastors, or group leaders to build and assign fully custom devotionals for their own communities.

Abide N Me gives group leaders control over the reading schedule while giving members a focused daily experience where they can **read or listen to Scripture, resume where they left off, and mark assigned passages complete**.

## What the app does

### Custom devotional and reading plans

Group owners and administrators can create Scripture plans for the people they lead.

Plans support two creation modes:

- **Automatic plans** — select one or more books of the Bible, choose a start date, duration, and reading days, and Abide N Me distributes the selected Scripture across the schedule.
- **Manual plans** — define each devotional day individually, including specific passages, a title, notes, and a discussion or reflection question.

Plans are attached directly to a group, making them useful for:

- Family Bible reading
- Parent-led devotionals
- Church reading plans
- Small groups
- Discipleship groups
- Youth ministries
- Pastor-led congregational reading

Leaders can later edit plan metadata or shift the start date, and group owners can manage plans assigned to their groups.

### Today view

Members receive a dedicated **Today** screen containing the Scripture assigned for the current date.

The app tracks each assigned passage independently so members can:

- Open the day's reading
- Read the assigned Scripture in context
- Resume an unfinished passage
- Track reading progress
- Mark passages complete
- See completion progress for other members of the group

This keeps the focus on actually completing the group's devotional rather than simply browsing a reading-plan calendar.

### Built-in Bible reader

Abide N Me includes its own Bible reader backed by Scripture stored in the application's database.

Reader features include:

- Translation selection
- Book and chapter navigation
- Previous/next chapter controls
- Verse highlighting
- Bookmarks
- Private verse notes
- Remembered reader position
- Scripture reference parsing for reading plans

The repository currently contains import definitions for several public-domain translations, including:

- **Douay-Rheims 1899 American Edition** — Catholic 73-book canon
- **World English Bible, Catholic Edition** — Catholic 73-book canon
- **King James Version** — Protestant 66-book canon
- **Berean Standard Bible** — Protestant 66-book canon

Bible packages are imported from USFM source archives rather than hard-coded into the application.

### Scripture audio

The reader includes a provider-neutral audio layer and playback-progress support.

When an approved recorded-audio provider is configured, the backend can expose normalized chapter/audio metadata without exposing provider credentials to the browser.

When recorded audio is unavailable, Abide N Me can also use device/browser text-to-speech to read the displayed chapter aloud. Playback speed controls are included in the reader.

Audio configuration is intentionally server-side and provider-neutral so licensing, attribution, host restrictions, API credentials, and Catholic-canon coverage can be evaluated before enabling a particular provider.

### Private groups

Users can create private Scripture-reading groups and invite others using a permanent group code or shareable invitation link.

Groups support:

- Owner, admin, and member roles
- Permanent join codes
- Shareable invitation links
- Member lists and profile pictures
- Plan assignment
- Member progress visibility
- Member role management
- Member removal
- Group archival/deletion by the owner

A parent might create a group for a household; a pastor could create one for a congregation or ministry; and a small-group leader could use one for a discipleship cohort.

### Simple accounts

Accounts are username-based rather than email-dependent. A user can create an account with:

- Display name
- Unique username
- Password

Email is not required for basic account creation, which keeps onboarding simple for families and younger group members.

Users can also upload a profile picture used throughout group and progress views.

## Why Abide N Me exists

The goal is not merely to build another Bible reader.

Abide N Me is designed around **spiritual leadership and shared accountability**.

A parent should be able to decide, "Our family is going through Luke over the next month," build that plan, add a short reflection or discussion question, and have every family member see the same assigned reading that day.

A pastor should be able to create a custom devotional series that follows a sermon series or parish/church emphasis without needing the devotional to be accepted into a third-party Bible app's public library first.

The emphasis is therefore:

**Leader-created plans + Scripture itself + group participation + visible progress.**

## Application architecture

Abide N Me is intentionally lightweight and can run on conventional PHP/MySQL hosting.

### Web application

- HTML5
- Vanilla JavaScript ES modules
- CSS
- Progressive Web App manifest
- Service worker/offline shell

The main client modules are organized by feature under `assets/js/`, including authentication, Bible reading, groups, plans, today's assignments, audio, routing, PWA behavior, and native integration.

### Backend

- PHP 8+
- PDO
- JSON API endpoints
- MySQL / MariaDB-compatible relational schema

Backend application services live under `src/`, while public API endpoints live under `api/`.

Major service areas include:

- Authentication/session handling
- Groups and membership
- Reading-plan creation and scheduling
- Daily assignments
- Passage progress
- Bible reading and reference parsing
- Scripture annotations
- Audio metadata and playback progress
- Rate limiting and request validation

### Mobile

The same web application can be packaged as a native application using **Capacitor 7**.

The `mobile/` project contains Android/iOS Capacitor configuration, asset generation, native text-to-speech integration, web asset synchronization, and Android build helpers.

## Repository structure

```text
api/                 Public PHP JSON endpoints
assets/
  css/               Application styling
  icons/             PWA/application icons
  js/                Browser application modules
bin/                  CLI utilities, including Bible import
config/               Private configuration template

database/
  schema.sql          Base relational schema
  migrations/         Incremental database migrations
  bible-sources.json  Bible package/source definitions

docs/                 Implementation and deployment notes
mobile/               Capacitor native application wrapper
src/                  PHP application/domain services
storage/
  cache/              Runtime cache
  imports/            Bible USFM source packages
  logs/               Runtime logs
  reports/            Import/validation reports

tests/                PHP and Node-based contract/behavior tests
index.html             Main SPA/PWA shell
manifest.webmanifest   PWA manifest
service-worker.js      PWA service worker
```

## Local/server setup

### Requirements

A typical deployment requires:

- PHP 8+
- MySQL or MariaDB
- Apache-compatible hosting or equivalent PHP web serving
- PHP PDO MySQL support
- PHP CLI access for Scripture imports
- Node.js/npm only when working with the Capacitor mobile project or JavaScript tests

### 1. Clone the repository

```bash
git clone https://github.com/noeljbass/Abide-N-Me.git
cd Abide-N-Me
```

### 2. Create the database

Create an empty MySQL database and import:

```text
database/schema.sql
```

Then apply the SQL files in `database/migrations/` in numeric order.

The migrations add features such as the Catholic canon, Bible-import metadata, public passage identifiers, avatars, additional translations, reader position, username accounts, and permanent group codes.

### 3. Configure the application

Copy:

```text
config/config.example.php
```

to either:

```text
config/local.php
```

or:

```text
config/config.php
```

and provide your private database settings.

Environment variables may also be used where supported by the hosting environment.

**Do not commit database credentials or audio-provider API keys.**

The example configuration includes application, database, and optional audio-provider settings.

### 4. Verify the installation

Open:

```text
/api/health.php
```

A properly configured installation should report that the database is connected and the schema is ready.

## Importing Bible translations

Bible text is imported through the CLI importer rather than being embedded in `schema.sql`.

Source metadata is stored in:

```text
database/bible-sources.json
```

and packages are placed under:

```text
storage/imports/
```

A package can be validated before import:

```bash
php bin/import-bible.php --source=engDRA --validate-only
```

Then imported with:

```bash
php bin/import-bible.php --source=engDRA
```

Other configured source identifiers include `eng-kjv`, `eng-web-c`, and `engbsb`.

See `storage/imports/README.md`, `docs/bible-source.md`, and `docs/ionos-bible-import.md` for additional source and deployment notes.

## Audio configuration

Recorded Scripture audio is disabled by default until a provider has been explicitly configured and approved.

Relevant private settings include:

```text
AUDIO_ENABLED
AUDIO_PROVIDER
AUDIO_API_BASE_URL
AUDIO_API_KEY
AUDIO_ALLOWED_HOSTS
AUDIO_REQUEST_TIMEOUT_SECONDS
```

Provider API keys remain server-side. The client receives normalized audio metadata rather than provider credentials.

Browser/device text-to-speech can operate independently of a recorded-audio provider.

See `docs/audio-foundation.md` for details.

## Native Android/iOS builds

The Capacitor project lives in `mobile/`.

```bash
cd mobile
npm install
npm run sync
```

Useful commands include:

```bash
npm run open:android
npm run open:ios
npm run run:android
npm run run:ios
npm run build:apk
npm run build:aab
```

See `docs/capacitor-setup.md` for the full native setup and deployment workflow.

## Security and privacy notes

The application includes server-side session/authentication handling, CSRF protection for authenticated writes, rate limiting, non-sequential public identifiers for assigned passages, restricted private configuration directories, and server-only handling of external audio credentials.

Bible notes created through the reader are private user annotations rather than group-shared comments.

Public deployments should serve the application over HTTPS and keep `config/`, `src/`, `database/`, and private runtime data inaccessible from direct web requests.

## Testing

The `tests/` directory contains focused PHP and Node-based tests covering areas such as:

- Authentication contracts
- Group service behavior
- Group invitations
- Plan-builder modes
- Group plan management
- Bible USFM package parsing
- Translation selection
- Reader position
- Audio state and mobile audio behavior
- Today's audio assignments
- Legal pages

## Current project direction

Abide N Me is being developed as a practical tool for **custom, leader-directed Scripture devotionals** rather than as a general-purpose social Bible platform.

Core priorities are:

1. Make it easy for parents and ministry leaders to create a plan.
2. Make joining a group extremely simple.
3. Put today's Scripture immediately in front of the member.
4. Let users either read or listen.
5. Preserve progress and make completion visible to the group.
6. Support Catholic Scripture while retaining additional public-domain Bible translations where appropriate.
7. Keep the app deployable as both a PWA and native mobile application.

## Project name

The codebase originated under the working name **Feed My Sheep**. Some PHP namespaces and internal historical references still use `FeedMySheep`, while the public application is branded **Abide N Me**.

## License and Scripture rights

No blanket software license is currently declared in this repository.

Bible translations have their own source and rights metadata. The currently configured Scripture sources are marked as public domain in `database/bible-sources.json`; source provenance, package hashes, canon mappings, and publisher information are retained there.

Before redistributing or adding additional translations or recorded Scripture audio, verify the applicable licensing, attribution, caching, and distribution requirements.

---

**Abide N Me** — create the reading, gather your people, and abide in the Word together.
