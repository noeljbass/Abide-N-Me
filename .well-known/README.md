# Deep link association files

These two files are what let a tapped `https://abiden.me/join/CODE` link open the
installed app instead of the browser. Both must be served from the live site over
HTTPS, with no redirect, as `application/json`.

Neither works until the placeholders are replaced:

| File | Placeholder | Where the real value comes from |
| --- | --- | --- |
| `assetlinks.json` | `REPLACE_WITH_UPLOAD_KEY_SHA256` | `keytool -list -v -keystore abiden-upload.jks -alias abiden` |
| `assetlinks.json` | `REPLACE_WITH_PLAY_APP_SIGNING_SHA256` | Play Console → Test and release → Setup → App signing |
| `apple-app-site-association` | `REPLACE_WITH_APPLE_TEAM_ID` | Apple Developer → Membership → Team ID |

Leave both fingerprints in place for Android. The upload key is what signs the
build on your machine; Play re-signs it with a different key before delivery, and
a link only verifies if the fingerprint of the key that actually signed the
installed app is listed here.

Verify after deploying:

    curl -sI https://abiden.me/.well-known/assetlinks.json
    curl -sI https://abiden.me/.well-known/apple-app-site-association

Both should return `200` and `Content-Type: application/json`.
