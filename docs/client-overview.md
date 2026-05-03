# Filament Claude Assistant — Client Overview

## What is it?

**Filament Claude Assistant** is a ready-made AI chat widget that you drop into any Laravel admin panel. It adds a conversational interface — powered by Anthropic's Claude — that lets your users interact with your application using natural language instead of clicking through forms.

---

## What problems does it solve?

| Without it | With it |
|---|---|
| User fills 5 fields in a form to register an expense | User types *"Dentist appointment today, €120"* and it's saved |
| User navigates to Reports → runs filters → reads table | User asks *"How many incidents last month?"* and gets an answer |
| New user needs training to use the app | New user talks to the assistant naturally from day one |

---

## How it works (for clients)

```
User types a message in natural language
           │
           ▼
    Claude (Anthropic AI) reads the message
    and decides what to do
           │
    ┌──────┴──────────────────────┐
    │                             │
    ▼                             ▼
Answers directly          Calls a function
(explanations,            (creates a record,
 queries, advice)          searches data,
                           triggers an action)
           │                      │
           └──────────────────────┘
                      │
                      ▼
        Responds in plain language
        confirming what was done
```

The AI never invents data. It only reads and writes through the functions you define. If it doesn't know something, it says so.

---

## What it includes

- **Chat page** in the Filament admin panel with a clean, mobile-friendly UI
- **Dark mode** support out of the box
- **Conversation history** — Claude remembers the full context of the current session
- **Quick suggestions** — configurable chips below the input for common actions
- **Tool use** — Claude can call PHP functions in your app (create records, search data, etc.)
- **Error handling** — clear messages when the API key is missing or there's a connection issue

---

## Pricing & API costs

The plugin itself is **free and open source** (MIT licence).

API calls are billed by Anthropic based on tokens (words) processed:

| Model | Input | Output | Typical cost per conversation |
|---|---|---|---|
| Claude Haiku 4.5 *(default)* | $1 / MTok | $5 / MTok | ~$0.001–0.005 |
| Claude Sonnet 4.5 | $3 / MTok | $15 / MTok | ~$0.005–0.02 |

> A typical short message + response ≈ 500–1500 tokens.
> With Haiku (the default), **1,000 messages cost roughly €1–5** depending on length.

You need an Anthropic account and API key: [console.anthropic.com](https://console.anthropic.com)

---

## What your users see

A chat interface in the admin sidebar:

```
┌─────────────────────────────────────────────┐
│ 🤖 AI Assistant                              │
├─────────────────────────────────────────────┤
│                                             │
│  ✨  Hi! I'm your AI assistant.             │
│      How can I help you today?              │
│                                             │
│  Quick actions:                             │
│  [ Create task ] [ Search client ]          │
│  [ Show activity ]                          │
│                                             │
│     ┌──────────────────────────┐            │
│     │ Dentist appointment €120 │  👤        │
│     └──────────────────────────┘            │
│                                             │
│  ✨  I've registered an expense of          │
│      €120 for "Dentist appointment"         │
│      on today's date.                       │
│                                             │
├─────────────────────────────────────────────┤
│  [ Write a message…            ] [➤] [🗑]   │
│  Enter to send · Shift+Enter for new line   │
└─────────────────────────────────────────────┘
```

---

## What you configure per project

Everything the assistant can do is defined by the developer for each specific project. There is no generic AI that accesses all your data — it only has access to the tools you explicitly create:

- **System prompt** — personality and instructions ("You are an assistant for X app, you help users manage Y")
- **Tools** — each tool is a PHP class that can read or write to your database
- **Context** — which user's data to access (always scoped to the logged-in user)
- **Suggestions** — quick-action chips shown below the input

---

## Security

- The assistant only calls functions you explicitly define and register
- Each call is scoped to the authenticated user via `$context` (no cross-user data access)
- API keys are stored server-side in `.env`, never exposed to the browser
- No data is stored by Anthropic beyond the current API request (see [Anthropic's privacy policy](https://www.anthropic.com/privacy))
- Tool execution runs inside your own server — Claude receives only the return value

---

## Integration effort

| Task | Time estimate |
|---|---|
| Install package + configure API key | 5 minutes |
| Register plugin in Filament panel | 5 minutes |
| Write one Tool (e.g. create a record) | 30–60 minutes |
| Full assistant with 3–5 tools | Half day |

---

## Requirements

- Laravel 11 or 12
- Filament 3 or 4
- PHP 8.2+
- Anthropic API key
- Internet access from the server (to reach `api.anthropic.com`)
