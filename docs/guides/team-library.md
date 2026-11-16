# Set up a team Library

I wanted one place where my team could drop screenshots and recordings and find each other's work later, without another account to manage. Everyone on the team already has a WordPress login, so the team Library is just a WordPress site with the PressShot plugin on it. Your WordPress account is your sign-in, so there's no separate account to create.

## How a team site works

A team site is shared. Everyone on the site who can upload files sees every shot at `/shots`, and folders and tags belong to the whole team. There's no private corner on a team site and no manager view; if you want shots only you can see, set up a [private Library](private-library.md) on a site only you use.

Be clear with your team about one more thing: **WordPress administrators can see every upload** in wp-admin's Media Library, whether or not the plugin is active. That's how WordPress works, and the plugin doesn't pretend otherwise.

Who can do what follows WordPress's own roles:

| Role | See, download, organize, and copy existing links | Create, revoke, or change the expiry of a shot's link, and delete | Share a whole folder |
| --- | --- | --- | --- |
| Author | Every shot | Their own shots | No |
| Editor | Every shot | Anyone's shots | Yes |
| Administrator | Every shot | Anyone's shots | Yes |

Anyone on the team can copy a link that already exists, on any shot. Making, revoking, or changing a link is what the role decides.

Contributors and Subscribers can't upload files, so they get a short "You don't have access to the Library on this site." message instead of the Library.

## What you need

- WordPress 7.1 or newer. Self-hosted works, and so does a WordPress.com plan that allows plugins.
- Pretty permalinks. Any setting in **Settings → Permalinks** except Plain.
- HTTPS, so people can approve the Mac app with an application password.
- **No shared page cache on `/shots`.** The Library page carries a sign-in token that belongs to the person viewing it. If a caching plugin or your host's page cache serves one person's copy of `/shots` to someone else, the Library breaks for them. Most caches already skip signed-in visitors; if yours doesn't, exclude `/shots` (and `/s/`, the share pages) from it.

## Set it up

1. **Install the plugin.** In wp-admin, go to **Plugins → Add New → Upload Plugin**, choose `pressshot.zip`, and activate it. Activation sets up the `/shots` and `/s/` addresses.
2. **Check your permalinks.** Open **Settings → Permalinks** and make sure it isn't set to Plain. Click **Save Changes** once, even if nothing changed; it's a cheap way to make sure `/shots` is wired up.
3. **Add your teammates.** Go to **Users → Add New** and give each person the Author role, or Editor for anyone who should share folders and clean up after others. Everyone gets their own account; never share one login.
4. **Visit `/shots`.** You'll see the Library. It's empty until someone uploads.

## Connect each Mac

Each person does this on their own Mac with their own account.

1. In PressShot, open **Settings → Upload destinations → Add destination** and choose **Connect a site directly**.
2. Enter the team site's address and click **Connect**.
3. The site's own **Approve** page opens in the browser. Sign in with your WordPress account and approve PressShot.
4. Back in the app, save the connection and make it your primary destination if this is where most of your shots should go.

WordPress creates an application password for that Mac during the approval. It lives in that Mac's Keychain, and nobody else ever sees it.

The PressShot Mac app update that registers uploads as shots is still on its way. Until it's out, uploads from PressShot 1.0.x land in the Media Library as plain media and don't appear at `/shots`.

## Sharing outside the team

Every shot gets an anyone-with-link page at `/s/…` when it's registered, and that's the link the Mac app copies after upload. People outside the team can open it without signing in. Editors and Administrators can also share a whole folder, which includes its subfolders and anything added later.

You can set an expiry on a link or revoke it from the share dialog in the Library. Revoking or expiring a link closes its share page right away.

A folder link shares everything in that folder and the folders inside it. So if a shot sits in a shared folder, revoking the shot's own link doesn't unshare it; people with the folder's link can still see it. The shot's share dialog tells you when that's the case and names the folder. To stop sharing the shot, move it out of that folder or revoke the folder's link.

Here's what the plugin protects and what it doesn't. It hides shots, folders, and tags from WordPress's public listings (the media and folder REST routes, attachment pages, and embeds), and its own routes check who's asking on every request. The image or video file itself, though, still lives at a normal uploads address, like anything else in the Media Library. That address isn't secret. Anyone who has the exact address can open the file, and revoking a link doesn't change that. Only a private WordPress.com site or server rules that block the uploads folder protect the files themselves. (A private site also blocks the `/s/` share pages, so outside sharing stops working there.)

For recordings stored in VideoPress, we're still checking whether revoking also stops the VideoPress video itself from playing for someone who already has its address. Until that's confirmed, treat a revoked VideoPress link as "the page is gone," not "the video is locked." Anything already downloaded can't be pulled back, of course.

## When someone leaves

Remove their WordPress account, or open their profile under **Users** and revoke the application password for their Mac. Either way, their Mac stops uploading right away. If you delete the account, WordPress asks what to do with their content; attribute it to someone else to keep their shots in the Library.

## Trash

Deleted shots go to Trash at `/shots` for 30 days by default, then WordPress removes them for good. Restoring a shot doesn't bring back its old share link; make a new one if you need it.

Questions or something that doesn't work the way this says? Tell me. I'd love to hear how your team ends up using it.
