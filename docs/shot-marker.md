# The PressShot shot marker

A shot is a WordPress attachment that a capture app has registered with the PressShot plugin. Only registered attachments show up in the Library at `/shots`; everything else in the Media Library stays plain media. This page is the whole contract, so any capture app can send shots to a site running the plugin.

## Two requests

**1. Upload the file with core WordPress.**

```
POST /wp-json/wp/v2/media
Authorization: Basic <username:application-password>
Content-Type: image/png
Content-Disposition: attachment; filename="Shot 2026-11-14 at 10.42.png"

<file bytes>
```

Send images for screenshots and videos for recordings. Videos on a site with VideoPress go to VideoPress the same way they would from any other client. Keep the attachment `id` from the response.

**2. Register it as a shot.**

```
POST /wp-json/pressshot/v1/captures/{id}/register
Authorization: Basic <username:application-password>
Content-Type: application/json

{ "source": "pressshot" }
```

The response:

```json
{
  "id": 123,
  "source": "pressshot",
  "shareUrl": "https://example.com/s/Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4z"
}
```

- `id` is the attachment id you sent.
- `source` is the app name you sent.
- `shareUrl` is the shot's anyone-with-link page. Copy this to the clipboard. It opens without signing in, and it keeps working until someone revokes it or sets an expiry in the Library.

Registering twice is fine. You get the same `shareUrl` back as long as the link is still active; if someone revoked it, registering again makes a new one.

## What registering stores

| Meta key | Value |
| --- | --- |
| `_pressshot_capture` | `1`. This is the marker. |
| `_pressshot_source` | The `source` string, for example `pressshot`. |

Both keys start with an underscore, so they're protected meta and can't be set through the `meta` field on `wp/v2/media`. The register route is the only way in, and that's on purpose: it's where the plugin checks who you are and which app you are.

## Who can register

The person signed in (usually through an application password) has to:

- be able to upload files on the site (`upload_files`), and
- be the author of the attachment.

### VideoPress uploads

VideoPress creates an uploaded video's attachment in the name of the site's connection account, not the person who recorded it. To register one, also send the VideoPress ID your upload got back (the `x-videopress-upload-guid` response header):

```json
{ "source": "pressshot", "videopressGuid": "Ab3dEf6h" }
```

The plugin then makes you the attachment's author, but only if it's a VideoPress video that isn't a shot yet, it was created in the last 15 minutes, and the ID matches.

| Status | Code | When |
| --- | --- | --- |
| 400 | `pressshot_unknown_source` | The `source` isn't on the site's allowed list. |
| 401 | `pressshot_forbidden` | The attachment exists, but nobody is signed in. |
| 403 | `pressshot_forbidden` | The person can't upload files, or didn't upload this attachment. |
| 404 | `pressshot_not_found` | The id isn't an attachment, or it's in the Trash. |

## Allowed sources

The plugin accepts `pressshot` and nothing else by default. The list comes from the `pressshot_shot_sources` filter:

```php
add_filter(
	'pressshot_shot_sources',
	function ( $sources ) {
		$sources[] = 'example-app';
		return $sources;
	}
);
```

Put that in a small must-use plugin or your theme's `functions.php`. By convention a source is a plain lowercase string; pick a short name for your app and send exactly that.

## Adding a new app

1. Pick a source name for your app and send it in the register request.
2. Until the plugin ships with your name in its default list, a site owner adds it with the filter above.
3. To get it into the default list, get in touch with the PressShot maintainers with the name and a link to your app. New names go into the defaults once we've agreed on them together.

That's it. Folders, tags, sharing, and Trash all work the same for every source.
