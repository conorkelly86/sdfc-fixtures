# Claude Code Instructions

## Writing Style

**Never use em dashes (—) in any content generated for this project.** Use a comma, full stop, or reword the sentence instead.

---

## Important Note: Venue Name Change

**NFP Park** (formerly known as "Drom Soccer Park" or just "Drom") is the home venue for Salthill Devon FC. When generating content, always use "NFP Park" as the primary venue name for future requests.

---

## Updating Weekly Fixtures in WordPress

**When the user says "run the fixtures" or "update fixtures":**

Run this command:
```bash
env -u GITHUB_TOKEN -u GH_TOKEN gh workflow run fixtures.yml --repo conorkelly86/sdfc-fixtures
```

This triggers the GitHub Actions workflow, which:
1. Fetches the latest fixtures from Galway FA API
2. Filters for Salthill Devon home games only
3. Posts the HTML directly to the WordPress post (ID 4244)
4. Completes in about 15 seconds

The post updates automatically with no manual steps needed.

**Note on scheduling:** GitHub's scheduled runs are not firing on this repo. The workflow works perfectly when manually triggered. Until GitHub fixes it, manual weekly updates are the workaround.

---

## Workflow for Adding New Fixtures to live-stream-matches.html

When the user provides new match information to add to `live-stream-matches.html`:

1. **Read the file first** to understand the current structure
2. **Identify ALL fixtures by date** relative to TODAY's date (check `<env>` for current date)
3. **Make changes in ONE comprehensive edit** - don't fragment into multiple small edits
4. **Apply the correct Info labels based on date:**
   - **Future matches (today and later)**: Live Stream link to https://staydiasports.com/channel/salthilldevonfc
   - **Past matches (before today)**: View Recording link to https://staydiasports.com/channel/salthilldevonfc

## Match Entry Format

```html
<tr>
  <td data-label="Date">Oct 5, 2025</td>
  <td data-label="Time">14:00</td>
  <td data-label="Match">TEAM V OPPONENT (Pitch X)</td>
  <td data-label="Info">
    <a
      href="https://staydiasports.com/channel/salthilldevonfc"
      class="fixture-link"
      target="_blank"
      rel="noopener noreferrer"
      ><span>Live Stream</span>  <!-- or "View Recording" for past matches -->
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="1em"
        height="1em"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="lucide lucide-external-link"
      >
        <path d="M15 3h6v6" />
        <path d="M10 14L21 3" />
        <path
          d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"
        /></svg
    ></a>
  </td>
</tr>
```

## Key Principles

- **Reverse chronological order**: Newest fixtures at the top
- **Batch all similar changes**: Update all fixtures in one edit
- **Date-aware labeling**: Use today's date to determine Live Stream vs View Recording
- **Complete structure**: Always include full HTML with proper formatting
- **30-day retention policy**: Only include fixtures from the past 30 days. **IMPORTANT: Remove fixtures older than 30 days completely from the file - do not just hide them with `class="fixtures-hidden"`. Delete the entire `<tr>` element for any fixture that is 30 days or older from today's date.**

### Expanded/Collapsed Table Logic

The `live-stream-matches.html` file uses a collapsed view to improve page load performance:

- **First 6 visible fixtures**: The 6 most recent fixtures are shown by default (no class attribute)
- **Hidden fixtures**: All fixtures after the first 6 have `class="fixtures-hidden"` added to the `<tr>` tag
- **Load Previous Fixtures button**: Clicking this button reveals all hidden fixtures
- **When adding new fixtures**:
  1. Add new fixtures at the top without the `fixtures-hidden` class
  2. Count down to ensure only the 6 most recent fixtures are visible
  3. Add `class="fixtures-hidden"` to the 7th fixture row and all rows below it
  4. Update "Live Stream" vs "View Recording" labels based on today's date

**Example structure:**
```html
<tr><!-- Fixture 1 - visible --></tr>
<tr><!-- Fixture 2 - visible --></tr>
<tr><!-- Fixture 3 - visible --></tr>
<tr><!-- Fixture 4 - visible --></tr>
<tr><!-- Fixture 5 - visible --></tr>
<tr><!-- Fixture 6 - visible --></tr>
<tr class="fixtures-hidden"><!-- Fixture 7 - hidden --></tr>
<tr class="fixtures-hidden"><!-- Fixture 8 - hidden --></tr>
<!-- etc -->
```

---

## Workflow for Generating Week in Review from Google Form Data

When the user provides match result rows from the Google Form to populate `week-in-review.html`:

### Google Form Column Headers
```
Timestamp | Email Address | Age group | Team | Opposition Team | Match Date | Your name | Score | Match report (if applicable) | Photos | Who scored | Who got assists | Who made an appearance
```

### Process Steps

1. **Parse the data** - Extract key information:
   - Age group (e.g., U13A, U15C)
   - Teams (Home vs Opposition)
   - Match Date
   - Score
   - Match report (if provided)
   - Scorers and assists
   - Photos (Google Drive links)

2. **Generate WordPress blocks** for each match using this structure:

```html
<!-- wp:heading {"level":2} -->
<h2>[Create engaging headline from result]</h2>
<!-- /wp:heading -->

<!-- wp:image {"id":100,"sizeSlug":"large"} -->
<figure class="wp-block-image size-large">
  <img
    src="[Google Drive image URL]"
    alt="[Age group] team photo"
  />
</figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>
  [Match summary paragraph or full match report]
</p>
<!-- /wp:paragraph -->
```

### Content Guidelines

**Headline Format:**

**IMPORTANT: Keep headlines lighthearted and kid-friendly. Avoid aggressive or confrontational words like "dominate", "crush", "destroy". Use positive, celebratory language instead.**

- Wins: "[Age Group] [Boys/Girls] [Positive Descriptor] [Score] Victory/Win Over [Opponent]!"
  - Good examples: "U13A Boys Shine in 7-3 Victory Over Tuam Celtic!", "U12A Boys Celebrate 4-2 Win Over Ballaghdereen!"
  - Avoid: "U12A Boys Dominate...", "U13A Boys Crush...", "U15B Boys Destroy..."
  - Positive descriptors: Shine, Celebrate, Claim, Secure, Delight, Excel
- Draws: "[Age Group] [Boys/Girls] Battle to [Score] Draw With [Opponent]!"
- Losses: "[Age Group] [Boys/Girls] Hard-Fought Battle Against [Opponent]"

**Match Summary (when no detailed report provided):**
- Start with team name and result
- Highlight top scorers (hat-tricks, braces)
- Mention assists when provided
- End with positive team note

**Match Report (when provided in form):**
- Use the submitted report as-is
- Add scorer details at the end if not already included
- Keep all original formatting and tone

**Image Handling:**
- Convert Google Drive share links: `https://drive.google.com/open?id=XXXXX`
- To direct view format: `https://drive.google.com/uc?export=view&id=XXXXX`
- If image doesn't render in WordPress, user will need to download and upload to media library

### Scoring Details Format

- **Hat-trick or more**: "[Player] led the scoring with a hat-trick"
- **Brace**: "[Player] added a brace"
- **Single goal**: "[Player] and [Player] each found the net"
- **Assists**: "Great assists from [Player], [Player], and [Player]"

### Template Structure

Always maintain:
- Opening paragraph (intro to the week)
- Individual match sections (heading + optional image + summary/report)
- Closing paragraph with fixtures link
- WordPress block comment format throughout

### Example Transformation

**Input row:**
```
10/25/2025 13:37:34 | | U13A | Salthill Devon u13As | Tuam Celtic | 10/25/2025 | Keith Hevican | SDFC 7 - 3 Tuam Celtic | | https://drive.google.com/open?id=1HjRmYbB7... | Elliott McCullough 3, Noah Donahue 2, Finn Hevican 1, Evan Lydon 1 | Evan Lydon, Liam Monaghan, Elliott McCullough, Finn Hevican |
```

**Output:**
```html
<!-- wp:heading {"level":2} -->
<h2>U13A Boys Dominant 7-3 Victory Over Tuam Celtic!</h2>
<!-- /wp:heading -->

<!-- wp:image {"id":100,"sizeSlug":"large"} -->
<figure class="wp-block-image size-large">
  <img
    src="https://drive.google.com/uc?export=view&id=1HjRmYbB7..."
    alt="U13A Boys team photo"
  />
</figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>
  Our U13A Boys put on an impressive display, defeating Tuam Celtic 7-3! Elliott McCullough led the scoring with a hat-trick, Noah Donahue added a brace, and Finn Hevican and Evan Lydon each found the net. Great assists from Evan Lydon, Liam Monaghan, Elliott McCullough, and Finn Hevican. A fantastic team performance!
</p>
<!-- /wp:paragraph -->
```

### Key Notes

- **REMOVE ALL PREVIOUS WEEK'S CONTENT FIRST** - Each week is a fresh start. Delete all match sections from the previous week before adding new content
- **Make ONE comprehensive edit** - Replace all example matches in template with real data
- **Keep the intro and outro paragraphs** - Only replace the match sections
- **Order matches logically** - Senior teams first, then by age group (descending)
- **Boys vs Girls** - Alternate or group as makes sense for flow
- **Preserve WordPress formatting** - Always use proper block comments
