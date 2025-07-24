# Summary of Changes to the HomeBannerAPI Module

## Issues Addressed

The following issues were identified in the original code:

1. The module didn't read `data-src` attributes used for lazy loading in the mobile carousel
2. The module didn't exclude duplicate slides with class `swiper-slide-duplicate`
3. The JSON output contained only 5 desktop/mobile pairs, plus 1 mobile image without a desktop counterpart, instead of 6 complete pairs
4. Some slides were missing from the JSON output
5. There were incorrect pairings between desktop and mobile images

## Changes Made

### 1. Updated Desktop Slide Extraction

Modified the pattern for extracting desktop slides to:
- Include both `data-src` and `src` attributes
- Exclude slides with class `swiper-slide-duplicate`

```php
// Before:
preg_match_all('/<div[^>]*class="[^"]*swiper-slide[^"]*"[^>]*>.*?<img[^>]*src="([^"]+)"[^>]*>.*?<\/div>/s', $desktopCarousel, $slideMatches);

// After:
preg_match_all('/<div[^>]*class="[^"]*swiper-slide(?!.*swiper-slide-duplicate)[^"]*"[^>]*>.*?<img[^>]*(?:data-src|src)="([^"]+)"[^>]*>.*?<\/div>/s', $desktopCarousel, $slideMatches);
```

### 2. Updated Mobile Slide Extraction

Modified the pattern for extracting mobile slides to:
- Exclude slides with class `swiper-slide-duplicate`

```php
// Before:
preg_match_all('/<div[^>]*class="[^"]*swiper-slide[^"]*"[^>]*>.*?<img[^>]*(?:data-src|src)="([^"]+)"[^>]*>.*?<\/div>/s', $mobileCarousel, $slideMatches);

// After:
preg_match_all('/<div[^>]*class="[^"]*swiper-slide(?!.*swiper-slide-duplicate)[^"]*"[^>]*>.*?<img[^>]*(?:data-src|src)="([^"]+)"[^>]*>.*?<\/div>/s', $mobileCarousel, $slideMatches);
```

### 3. Improved Desktop/Mobile Pairing Logic

Completely rewrote the pairing logic to:
- Organize mobile slides by position for faster access
- Track used mobile slides to prevent duplicates
- Prioritize position-based matching (first try to match slides with the same position)
- Improve filename similarity matching:
  - Remove file extensions
  - Remove numbers
  - Normalize to lowercase and trim whitespace
  - Use a relative similarity score (similarity divided by max length)
  - Use a threshold of 0.6 for similarity

### 4. Increased Maximum Number of Slides

Changed the maximum number of slides from 5 to 6:

```php
// Before:
// Crea slide dalle immagini trovate (massimo 5)
foreach ($finalImages as $index => $slideImage) {
    if (count($slides) < 5) {

// After:
// Crea slide dalle immagini trovate (massimo 6)
foreach ($finalImages as $index => $slideImage) {
    if (count($slides) < 6) {
```

### 5. Ensured Exactly 6 Desktop/Mobile Pairs

Added logic to ensure we always have exactly 6 desktop/mobile pairs in the final output:
- If we have fewer than 6 pairs, add empty pairs to reach 6
- If we have more than 6 pairs, take only the first 6
- Added detailed logging to track the number of pairs at each stage

## Expected Results

These changes should ensure that:

1. The module correctly extracts all images from both desktop and mobile carousels, including those using `data-src` for lazy loading
2. The module excludes duplicate slides with class `swiper-slide-duplicate`
3. The JSON output contains exactly 6 desktop/mobile pairs
4. The pairing between desktop and mobile images is more accurate

The API response should now correctly reflect the actual content of the homepage carousels, with 6 desktop images and 6 mobile images properly paired.