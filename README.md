# Woori Church Weekly Sermons
### Version 1.5.7

## What it does

- Adds Dashboard > Weekly Sermons > Add Weekly Sermon.
- Creates ordinary WordPress Posts, so existing post-based widgets and pagination continue to work.
- Automatically assigns the "Sermons" category.
- Stores sermon date, speaker, Bible verse, YouTube URL, and two order-of-service image attachment IDs.
- Uses the first order-of-service image as the WordPress Featured Image.
- Uses the native WordPress Media Library uploader.
- Adds a Sermon Details metabox to normal Post edit screens.
- Provides Elementor-Free-friendly shortcodes.
- Optional shared Elementor template for all posts in the Sermons category, without changing their post URLs or the existing Services archive.

## Installation

1. In WordPress go to Plugins > Add New > Upload Plugin.
2. Upload the woori-church-sermons.zip file.
3. Activate it.
4. A "Weekly Sermons" menu will appear in wp-admin.
5. To use one reusable sermon layout, create and save an Elementor Saved Template, then open Weekly Sermons → Template Settings and select it. Leave the setting disabled to keep the current individual post layouts.

## Shortcodes

### Current sermon/post:

- `[sermon_title]`
- `[sermon_date]`
- `[sermon_speaker]`
- `[sermon_verse]`
- `[sermon_youtube]`
- `[sermon_permalink]`
- `[sermon_order_1]`
- `[sermon_order_2]`
- `[sermon_order_carousel]` — both images in one carousel
- `[sermon_video]`

### Aliases for the shared Elementor template (current post):

- `[current_sermon_title]`
- `[current_sermon_date]`
- `[current_sermon_speaker]`
- `[current_sermon_verse]`
- `[current_sermon_video]`
- `[current_sermon_order_1]`
- `[current_sermon_order_2]`
- `[current_sermon_order_carousel]` — both images in one carousel
- `[current_sermon_youtube]`

### Latest published sermon:

- `[latest_sermon_title]`
- `[latest_sermon_date]`
- `[latest_sermon_speaker]`
- `[latest_sermon_verse]`
- `[latest_sermon_youtube]`
- `[latest_sermon_permalink]`
- `[latest_sermon_order_1]`
- `[latest_sermon_order_2]`
- `[latest_sermon_video]`

#### Optional image attributes:

- `[sermon_order_1 size="large" link="yes"]`

## Notes

- The plugin intentionally does NOT alter Ultimate Post Kit queries. Your existing Services page can continue querying Posts.
- To restrict the Services page to sermons only, use Ultimate Post Kit's Include/Category controls to select the "Sermons" category if your current widget supports it.
- Existing sermon posts are not automatically changed. Use the Sermon Details metabox on an existing post to add the structured fields. If you tick the "Save this post in the Sermons category" checkbox, it will be included in the sermon category.

## Shared template setup

- Build the complete reusable layout in Elementor and save it as a Saved Template.
- Add Elementor Shortcode widgets for the current_sermon_* shortcodes above.
- In Weekly Sermons → Template Settings, choose the saved template.
- Only normal Posts assigned to the Sermons category use the shared template. Other posts are unaffected.
- The template is optional and can be disabled from Template Settings. Elementor Pro Theme Builder may override this if a Single Post template is active.

## Changelog

- Version 1.5.7: On the Add Weekly Sermon form, automatically fetch the newest entry from the Sydney Woori Church YouTube Atom feed (when the URL is empty), offer manual refresh, and show a responsive playable YouTube embed preview. The feed cannot confirm whether a video is live. No YouTube API key is required.
- Version 1.5.7: Bible Passage includes editable chapter suffix (default 장) and verse suffix (default 절), preserves 편 for Psalms, and PDF prefill recognises '오늘의 말씀 : 여호수아 24:19-25' notation.
- Version 1.5.7: Chapter suffix is a dropdown beside chapter (장 / 편), verse suffix is fixed to 절 beside the verses, and a live generated Bible passage preview is displayed.
- Version 1.5.7: Changing the Bible book to 시편 automatically selects 편; other books select 장. Existing saved suffixes are preserved on form load.
- Version 1.5.7: Chapter suffix uses a text input beside the chapter again, defaulting to 편 for 시편 and 장 for other books. Live passage preview retained.
- Version 1.5.7: Removed the static Bible Passage / Built as description and the hardcoded 절 beside the verse field; live generated passage preview remains.
- Version 1.5.7: The optional manual Order of Service image-upload section is collapsible and closed by default. Expand it when PDF-to-PNG conversion fails or you need to select images manually.
- Version 1.5.7: Recognize reordered PDF text such as ": 24:19-25 오늘의 말씀 여호수아" from the 25 October bulletin. Version 1.5.7: Date-aware YouTube lookup uses service date from PDF or form; selects a matching recent feed video for past dates, otherwise the newest available video. Keeps manual override and preview.Version 1.5.7: Bible verse ranges accept comma-separated sections (e.g. 전도서 4장 1절, 9-12절) in PDF prefill, saved values, edit form and live preview.
- Version 1.5.9: Based on v1.5.7. YouTube Data API v3 lookup replaces public feed and channel scraping. Configure API key under Weekly Sermons > YouTube API Settings; the key is stored server-side. Uses channel uploads playlist (up to 500 recent uploads), matches service date using publication date and nearby livestream start metadata, otherwise falls back to latest. Keeps manual URL override and preview.
