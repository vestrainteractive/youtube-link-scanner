# youtube-link-scanner
Quick and dirty WP plugin to scan all posts for missing, broken, low-view, and high-dislike count videos.

Note:  A YouTube API is required for this plugin.

To use:
1.  Upload the contents of this repo to your /wp-content/plugins folder on your hosting platform.
2.  Go to your plugins admin panel in WordPress and Activate the plugin
3.  Look for YouTube Scanner link on the left admin-menu...click to open.
4.  Enter your YT API ID and press save.  If there is a problem with your API, the plugin will let you know.
5.  Press Start Scan.  Any posts containing videos that are missing, broken, low-view, or have a high dislike count will be linked in the results list.

if post > 6 months AND viewCount < 100, the status will be LOW VIEWS and colored purple
if privacyStatus = private or unlisted the status will be PRIVATE and colored red
if uploadStatus = deleted, the status will be DELETED and be colored BOLD RED
if title contains "[moved]" or "[Moved]" (without the quotes), the status should be Moved and colored orange <--site specific to me and I'm not going to bother removing it.
if dislikeCount > 5, the status should be shitty and colored brown <--shitty will be changed in the next version.
