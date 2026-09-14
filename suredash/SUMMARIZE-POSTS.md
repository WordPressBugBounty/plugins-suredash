# Summarize Posts

Long discussion posts are where members quietly drop off. **Summarize Posts** adds a small
sparkle button to a post's header — one click and members get a short, AI-written recap above
the post, so they can decide in a few seconds whether the full thread is worth their time.

The summary is written once and reused for everyone afterwards, so the community pays for one
AI call per post, not one per reader.

---

## What members see

A **Summarize** (✨) button appears in the top-right corner of a post, next to Bookmark.

Clicking it opens a panel directly under the post title containing:

- **A short paragraph** — two to three sentences, at most 60 words, describing what the post
  is about and what it asks for or announces.
- **Two to five key points** — the concrete specifics: decisions, questions, dates, numbers,
  feature names.

The first member to click waits a moment while the summary is generated; it then types itself
in word by word. Everyone after that sees it appear instantly. Clicking the button again — or
the **×** in the panel — closes it.

Nothing about the original post changes. The summary sits above it and never replaces it.

---

## Where the button appears

| Location | Shows the button? |
| --- | --- |
| Single post page | Yes |
| Quick view (the post popup) | Yes |
| Space feed, when **Content Preview** is set to **Full** | Yes |
| Space feed, when **Content Preview** is set to **Excerpt** | No |

The feed only shows the button on **Full** previews. On an excerpt the member is looking at a
few trimmed lines rather than the post itself, so there is nothing on screen worth summarizing —
they should open the post instead.

The button is also hidden when:

- The post is **shorter than roughly 400 characters** — it is already its own summary.
- The post is **locked** or restricted for that member.
- The visitor is **logged out**.
- The post is not a **discussion post** (courses, events and other content types are excluded).
- The post is **not published** — a draft, a pending post awaiting moderation, a scheduled post
  or something in the trash. Its own author and portal managers still get the button; nobody
  else can summarize it.

---

## Turning it on

Summarize Posts is **off by default on every site**, new and existing. Summarizing sends the
text of a post to a third-party AI provider, so it is never switched on for you — an admin has
to opt in deliberately.

Two things are needed.

### 1. Connect an AI provider

Go to **WordPress Admin → Settings → Connectors** and connect an AI provider — OpenAI,
Anthropic, Google Gemini, or anything else your site supports — by adding its API key.

> **Requires WordPress 7.0 or higher.** The Connectors screen and the AI features that use it
> are part of WordPress core from 7.0 onward. On older versions the button never appears and
> the setting does nothing.

The AI usage is billed by your provider, on your own account. SureDash does not add a fee, and
does not route anything through its own servers.

### 2. Switch on the setting

Go to **SureDash → Settings → AI Features & MCP** and turn on **Summarize Posts**.

If no provider is connected yet, the toggle still works — it just tells you the button will
stay hidden until a key is added. Connect one and the feature lights up on its own; you don't
have to come back to this screen.

### Choosing which provider writes the summaries

When more than one provider is connected, a **Summary Provider** dropdown appears under the
toggle.

- **Automatic** (default) — uses the first connected provider.
- **A specific provider** — always use that one.

If you later remove the key for the provider you picked, SureDash falls back to another
connected provider rather than breaking the button.

---

## How summaries stay current

Each summary is stored with a fingerprint of the post's title and content.

When someone edits the post, the fingerprint stops matching and the next member to open the
summary gets a freshly generated one. This works no matter how the post was edited — the
front-end editor, the WordPress admin, the REST API — so summaries never quietly go stale
against a rewritten post.

---

## Good to know

**What gets sent to the provider.** Only the post's title and its text — up to about 24,000
characters. Shortcodes, HTML and embeds are stripped first. Comments, member profiles, private
space content and anything else on your site are never included.

**Members only see posts they can already read.** Asking for a summary runs the same permission
check as opening the post, so a summary can never be used to peek at a restricted space.

**Posts can't hijack the AI.** Both the post's title and its body are passed to the model as
data, sealed inside a delimiter that is randomly named on every single request, and the
instructions tell it to treat anything resembling a command — "ignore previous instructions", a
fake system prompt, a request to reveal the prompt — as ordinary text to summarize. Because a
member cannot guess the delimiter, they cannot write a post (or a post *title*) that breaks out
of it and steers the summary shown to everyone else.

**Summaries are plain text.** No links, images, formatting or scripts survive into the panel.

**There is a fair-use limit.** A member can trigger up to 20 *new* summaries every 15 minutes.
Opening a summary that already exists is free and never counts against this — the limit only
applies to summaries being written for the first time, so normal reading is unaffected. It
exists so no single account can run up your provider bill.

**Summaries are factual, not editorial.** The model is instructed to use only what is in the
post, in the post's own language, and never to add advice, opinion or praise. If a post is
mostly an image or a link with little text, the summary says so rather than inventing content.

**Turning the setting off** hides the button everywhere immediately. Summaries already
generated stay stored on their posts and come back if you switch it on again.

---

## Troubleshooting

**The button doesn't appear anywhere.**
Check, in order: the setting is on under **SureDash → Settings → AI Features & MCP**; a provider
is connected under **Settings → Connectors** and its key is valid; the site is on WordPress 7.0
or higher; and you are logged in.

**The button appears on the single post but not in the feed.**
That space's **Content Preview** is set to **Excerpt**. Switch it to **Full** in the space
settings, or open the post.

**The button appears on some posts but not others.**
Short posts — under roughly 400 characters — never get one. Neither do locked posts, or content
types other than discussion posts.

**"No AI provider is connected."**
The key was removed, or its plugin was deactivated. Reconnect it under **Settings → Connectors**.
Only administrators see this message — members get the neutral "could not summarize" wording
instead, since configuration state is not something they can act on.

**"Could not summarize this post right now."**
The AI provider could not be reached — an expired key, a provider-side rate limit, or a network
problem. **Administrators** see the provider's actual error message in place of this one, which
usually says which of the three it is; everyone else gets the neutral wording, because the raw
message can contain account and endpoint detail. With `WP_DEBUG` on, the full message is also
written to the WordPress debug log.

After a failed attempt the post is left alone for five minutes before it can be tried again, so
a broken connection cannot be retried repeatedly at your expense. Fix the provider, then wait
out the five minutes.

**"This summary is being written right now."**
Someone else clicked Summarize on the same post a moment earlier. Only one summary per post is
generated at a time, so the community is never billed twice for the same post. Wait a few
seconds and click again.

**"You have requested a lot of summaries just now."**
That account has hit the fair-use limit of 20 new summaries per 15 minutes. It clears on its own.
