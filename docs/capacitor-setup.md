# Native builds (Capacitor)

The Android and iOS apps are the same front end that runs at abiden.me, packaged
into a native shell. Nothing is duplicated: `mobile/scripts/sync-www.mjs` copies
`index.html`, `privacy.html`, `terms.html` and `assets/` into `mobile/www` before
each build, and the app talks to the live PHP API over HTTPS at runtime.

## The one thing worth understanding first

PHP cannot run inside the app. The package contains HTML, CSS and JavaScript
only, so `https://abiden.me` must stay up for the app to work at all — it is the
back end for every install. There is no offline mode and no bundled database.

That has a knock-on effect on sign-in. Inside the shell the pages load from
`https://localhost` (Android) or `capacitor://localhost` (iOS), which makes every
call to `abiden.me/api/` cross-origin. Session cookies are dropped in that
situation by default, and the symptom is an app where login appears to succeed
and then immediately forgets you. Two changes prevent it:

1. `CapacitorHttp` is enabled in `mobile/capacitor.config.json`, so `fetch`
   goes through native networking rather than the WebView's own stack.
2. The app does not rely on the session cookie at all. It sends
   `X-App-Client: abiden-native` on every request; `JsonResponse` answers those
   requests with a `session` field carrying the session identifier, the app
   stores it, and sends it back as `X-Session-Id`, which `Session` picks up.
   This is what a bearer token would do, reusing the session machinery that is
   already in place. Relying on the cookie instead is what produced "Your
   session could not be verified": the request that fetched the CSRF token and
   the request that used it landed in two different sessions.
3. `src/Cors.php` sends cross-origin headers for the app's origins as a
   fallback, and `src/Session.php` relaxes the cookie to `SameSite=None` for
   them. Web traffic is untouched by any of this.

Two safeguards are worth knowing. The session identifier is only ever put in a
response for a request that identified itself as the app, so on the web the
cookie stays `httponly` and out of reach of script. And `session.use_strict_mode`
is on, so an identifier the server does not recognise is discarded rather than
adopted, which is what stops a supplied identifier from being a session fixation
hole.

## Layout

    FeedMySheep-main/
      index.html, assets/, api/, src/ ...   the existing site, unchanged in shape
      mobile/                                the Capacitor project
        capacitor.config.json
        package.json
        assets/                              icon and splash sources
        scripts/sync-www.mjs
        www/                                 generated, not committed
        android/                             created by `cap add android`
        ios/                                 created by `cap add ios`

Do not upload `mobile/` to IONOS. It is a build directory, not part of the site.

## What changed in the web app

| File | Change |
| --- | --- |
| `assets/js/native.js` | New. Platform detection, the API origin, deep link parsing, splash/status bar/back button wiring. |
| `assets/js/api.js` | API base and cookie mode now come from `native.js`. |
| `assets/js/pwa.js` | Service worker registration skipped in the native shell. |
| `assets/js/app.js` | Calls `initNative()` first. |
| `assets/js/groups.js` | Invitation links are built against abiden.me, and a link opened from outside the app now reaches the join card. |
| `service-worker.js` | Cache version bumped; `native.js` added to the shell list. |
| `src/Cors.php` | New. Cross-origin headers for the app origins only. |
| `src/bootstrap.php` | Applies the CORS handling before anything can write output. |
| `src/Session.php` | `SameSite=None` for app requests, `Lax` for the web. |
| `.htaccess` | Serves `apple-app-site-association` as JSON. |
| `.well-known/` | New. Deep link association files, placeholders inside. |

All of it is inert in a browser: `window.Capacitor` is undefined there, so every
new branch falls through to the behaviour the site has today.

## First-time setup

    cd mobile
    npm install
    npm run sync:www
    npx cap add android
    npx cap add ios          # macOS only
    npm run assets           # generates every icon and splash size
    npx cap sync

`npm run assets` reads `mobile/assets/icon.png` and `mobile/assets/splash.png`.
Both were generated from `assets/icons/icon.svg`, so they match the site exactly;
replace them with different artwork if you want, and re-run the command.

## Everyday loop

    cd mobile
    npm run sync             # copy the web app, then `cap sync`
    npm run open:android     # or open:ios

Any edit to `index.html` or `assets/` needs `npm run sync` before it shows up in
a build. Nothing is watched automatically.

## Requirements before the first build

Android builds need two things beyond Node: a JDK 17 or newer, and the Android
SDK. Installing Android Studio provides both — it bundles its own JDK and
downloads the SDK on first launch.

`scripts/gradle.mjs` finds them for you. Before each build it checks whether
`JAVA_HOME` points at a new enough JDK and, if not, looks in the places Android
Studio and the common JDK distributions install to. It also writes
`android/local.properties` pointing at the SDK, which Android Studio would
otherwise only create the first time you open the project in it.

The failure this avoids is worth recognising, because the message is unhelpful:

    Dependency requires at least JVM runtime version 11.
    This build uses a Java 8 JVM.

That means an old Java is first on the path. If the script cannot find a newer
one it will say so and stop. To fix it by hand:

    setx JAVA_HOME "C:\Program Files\Android\Android Studio\jbr"     (Windows)
    export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"   (macOS)

On Windows, open a new terminal afterwards so `setx` takes effect.

## Android

Debug build, no signing required:

    cd mobile
    npm run build:apk

The file lands at:

    mobile/android/app/build/outputs/apk/debug/app-debug.apk

That file installs on any device with "install unknown apps" allowed, and is the
fastest way to confirm the app works end to end.

The npm scripts are cross-platform: `scripts/gradle.mjs` picks `gradlew.bat` on
Windows and `./gradlew` elsewhere, so the same commands work on either machine.

### Sharing the APK privately

For a handful of family devices there is no need for a keystore, a Play listing
or any of the store phases below. Send `app-debug.apk` however you like — email,
Drive, a link on the site — and have each person allow installs from that source
once. A debug signature does not expire.

Two things follow from using the debug key. Android may show a Play Protect
warning on first install, which is dismissed under "Install anyway". And every
future build must keep using the same debug keystore, or the update will refuse
to install over the old one; that keystore lives at `~/.android/debug.keystore`
and is created automatically, so this only matters if you rebuild from a
different machine or user account.

### A harmless warning during `npm run assets`

After the Android icons are generated, the tool tries to produce PWA icons as
well and reports a missing `www/manifest.json`. The web app already ships its
manifest from the server and the native package does not use one, so nothing is
wrong. Check that `android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png` shows
your green mark rather than the default Capacitor logo, and carry on.

Release build for Play:

1. Create an upload key once:

        keytool -genkey -v -keystore abiden-upload.jks -keyalg RSA \
          -keysize 2048 -validity 10000 -alias abiden

   Keep this file and its passwords somewhere permanent. Losing it means you
   cannot ship an update to an existing listing.

2. `mobile/android/keystore.properties` (already git-ignored):

        storeFile=/absolute/path/to/abiden-upload.jks
        storePassword=...
        keyAlias=abiden
        keyPassword=...

3. In `mobile/android/app/build.gradle`, inside `android { }`:

        def keystorePropertiesFile = rootProject.file("keystore.properties")
        def keystoreProperties = new Properties()
        if (keystorePropertiesFile.exists()) {
            keystoreProperties.load(new FileInputStream(keystorePropertiesFile))
        }
        signingConfigs {
            release {
                storeFile file(keystoreProperties['storeFile'])
                storePassword keystoreProperties['storePassword']
                keyAlias keystoreProperties['keyAlias']
                keyPassword keystoreProperties['keyPassword']
            }
        }
        buildTypes {
            release {
                signingConfig signingConfigs.release
                minifyEnabled false
            }
        }

4. `npm run build:aab` produces
   `android/app/build/outputs/bundle/release/app-release.aab` for the Play
   Console.

### App links

In `mobile/android/app/src/main/AndroidManifest.xml`, inside the main
`<activity>`, alongside the existing `MAIN`/`LAUNCHER` filter:

    <intent-filter android:autoVerify="true">
      <action android:name="android.intent.action.VIEW" />
      <category android:name="android.intent.category.DEFAULT" />
      <category android:name="android.intent.category.BROWSABLE" />
      <data android:scheme="https" android:host="abiden.me" />
    </intent-filter>

Then fill in `.well-known/assetlinks.json` and deploy it. See
`.well-known/README.md`.

## iOS

    cd mobile
    npm run sync
    npx cap open ios

In Xcode: select the App target, set your Team under Signing & Capabilities, set
the bundle identifier to `me.abiden.app`, then run on a simulator or a connected
device.

For universal links, add the Associated Domains capability with
`applinks:abiden.me`, and fill in `.well-known/apple-app-site-association`.

Nothing in this app records audio, reads contacts, or uses location, so no usage
description strings are required in `Info.plist`.

## Reading a chapter aloud

Android's WebView does not implement the Web Speech API, so `speechSynthesis` is
undefined inside the app even though the device has a text-to-speech engine.
`@capacitor-community/text-to-speech` reaches the platform engine instead, and
`assets/js/native.js` exposes both behind one `speaker` object so that `audio.js`
does not care which is in use. The browser build is unchanged.

This only affects the fallback that reads the chapter text aloud. Recorded audio
from an audio provider, when one is configured, plays through the ordinary
`<audio>` element on every platform.

## Checking that the API connection works

Once the app is on a device, the fastest confirmation is to register or sign in.
If that succeeds and the account survives closing and reopening the app, the
cookie path is correct. If sign-in works but is forgotten on relaunch, look at:

- `https://abiden.me/api/health.php` in a browser, for session storage status
- Chrome DevTools at `chrome://inspect`, for an attached Android device
- Safari → Develop → your device, for iOS

## Version numbers

Android: `versionCode` and `versionName` in `mobile/android/app/build.gradle`.
`versionCode` must increase with every Play upload.

iOS: Version and Build in the Xcode target's General tab.
