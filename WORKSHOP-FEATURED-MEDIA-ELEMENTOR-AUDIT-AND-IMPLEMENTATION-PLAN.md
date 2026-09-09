# Workshop Featured Media in Elementor — Audit and Implementation Plan

## Requested outcome

On a single Workshop template that uses Elementor's native **Video** widget:

1. Show the workshop's Featured YouTube Video URL when one is saved.
2. Otherwise show the workshop's WordPress Featured Image in that same widget location.
3. Let an administrator enable or disable this fallback globally.

## Audit findings

### Existing data and validation

- The Workshop Details meta box saves the optional URL in `ggm_workshop_featured_video_url`.
- `ggm_sanitize_youtube_url()` accepts and normalizes individual YouTube watch, share, embed, Shorts, mobile, and privacy-enhanced URLs. Invalid, non-HTTPS, channel, and playlist-only values resolve to an empty string.
- The ordinary WordPress Featured Image remains independent of that URL and is already used by workshop cards and other existing templates.

### Existing Elementor integration

- `GGM_Elementor_Workshop_Video_Tag` exposes the saved URL as the **GGM Workshop Featured Video URL** dynamic tag in Elementor's URL category.
- It resolves the current published Workshop safely in singular, loop, and Elementor preview contexts.
- The tag deliberately prints nothing when a valid video URL is absent. The native Elementor Video widget therefore has no video source and does not automatically substitute the post's Featured Image.

### Design decision

Changing the dynamic tag to return an image URL would be incorrect: Elementor's Video widget would try to play that image. The fallback must instead happen while Elementor renders that specific Video widget.

## Implementation plan

1. Add a global setting, enabled by default, named **Enable featured-media fallback for Elementor Video widgets**.
2. Hook Elementor's `elementor/widget/render_content` filter after Elementor loads.
3. Process only the native `video` widget whose `youtube_url` field explicitly uses the GGM Workshop Featured Video URL dynamic tag. This avoids changing unrelated Elementor Video widgets.
4. Preserve Elementor's normal video output when the Workshop has a valid URL. When it does not, replace that widget's inner output with the current Workshop's full Featured Image, retaining WordPress image attributes and a meaningful alt value.
5. If no Featured Image exists, leave Elementor's original empty-video behavior untouched; there is no legitimate image to render.
6. Verify syntax, the setting save path, enabled/disabled behavior, video and image paths, and the no-image edge case.

## Administrator setup

1. In the Single Workshop template, use Elementor's native **Video** widget.
2. Set its YouTube URL using Dynamic Tags → **GGM Workshop Featured Video URL**.
3. In DZ LMS Settings → Workshop Shortcodes, leave the fallback setting enabled to use the Featured Image whenever that Workshop has no video URL.

No additional shortcode, CSS class, or duplicated image widget is required.

## Acceptance matrix

| Global setting | Valid Workshop video URL | Workshop Featured Image | Expected widget content |
| --- | --- | --- | --- |
| Enabled | Yes | Any | Elementor video |
| Enabled | No | Yes | WordPress Featured Image |
| Enabled | No | No | No replacement; native empty-video output |
| Disabled | Any | Any | Existing Elementor Video widget behavior |

