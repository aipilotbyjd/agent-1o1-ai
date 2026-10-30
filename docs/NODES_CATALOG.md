# Node Catalog Plan

## Context

`docs/WORKFLOWS_PLAN.md` defines `NodeContract` and `NodeRegistry`; this doc is the backlog of every node to port off the old project (`agent-1o1-api`), which already has ~150 working nodes across ~35 integration families under `app/Services/Workflows/Nodes/Apps/*`. Every node here is a **mechanical port**: same HTTP calls/SDK usage and config schema, moved into this project's `Nodes/` tree (`STRUCTURE.md`) and made to implement `NodeContract` instead of the old project's `ExecutableNode`. No new node logic is designed here — this is a checklist, not a design doc.

## Folder convention (from `STRUCTURE.md`, already committed)

```
app/Nodes/
  AiAutomation/        (category slug: ai-automation)
  TriggersEvents/       (category slug: triggers-events)
  FlowLogic/             (category slug: flow-logic)
  DataTransform/         (category slug: data-transform)
  Custom/                 (category slug: custom)
  Integrations/
    {Service}/*Node.php   (category slug: kebab-case service name, e.g. google-sheets, aws-s3)
```
Old project's equivalent is `Nodes/Core`, `Nodes/Flow`, `Nodes/Custom`, `Nodes/Apps/Data`, `Nodes/Apps/Ai`, `Nodes/Apps/{Service}` — the rename is folder-only; class bodies port as-is.

## Core / flow-control (not `NodeContract` — engine-driven, see `WORKFLOWS_PLAN.md`)

| Old class (`Services/Workflows/Nodes/Core|Flow`) | New path | Notes |
|---|---|---|
| `TransformNode` | `Nodes/DataTransform/TransformNode.php` | implements `NodeContract` — this one *does* do work (data reshaping), unlike the others in this table |
| `HttpRequestNode` | `Nodes/DataTransform/CallApiNode.php` | generic HTTP — implements `NodeContract` |
| `SetVariableNode` | `Nodes/DataTransform/SetVariableNode.php` | implements `NodeContract` |
| `AgentNode` | `Nodes/AiAutomation/AgentNode.php` | embeds an `Agent` session inside a workflow — depends on `AGENTS_PLAN.md` §1-3 |
| `HumanApprovalNode` | `Nodes/FlowLogic/HumanApprovalNode.php` | engine-driven, no `execute()` |
| `TriggerNode` | `Nodes/TriggersEvents/{Manual,Schedule,Webhook}TriggerNode.php` | split into three per `STRUCTURE.md`'s trigger-as-node-category design; see `TRIGGERS_PLAN.md` |
| `SubWorkflowNode` | `Nodes/FlowLogic/SubWorkflowNode.php` | engine-driven |
| `CodeNode` | `Nodes/DataTransform/RunCodeNode.php` | implements `NodeContract` — sandboxed code execution, needs its own security review at build time |
| `WaitNode` | `Nodes/FlowLogic/WaitNode.php` | engine-driven |
| `MergeNode` | `Nodes/FlowLogic/JoinPathsNode.php` | engine-driven |
| `DelayNode` | `Nodes/FlowLogic/DelayNode.php` | implements `NodeContract` (returns `{seconds}`, engine reads it for the actual delay) |
| `LoopNode` | `Nodes/FlowLogic/LoopNode.php` | engine-driven (`foreach` mode) / `NodeContract` (`map` mode) — see `WorkflowRunner::executeStep()`'s branch on `config.mode` |
| `ConditionNode` | `Nodes/FlowLogic/RouterNode.php` | implements `NodeContract` — evaluates a condition, outputs `result` for edges to match on |
| `Apps/Data/FilterNode` | `Nodes/FlowLogic/FilterNode.php` | implements `NodeContract` |

### Core node behaviour notes (as built)

- **One condition engine.** Filter, Router and every trigger's "Only run when" list decide through `Services\Workflows\ConditionEvaluator`, so an operator means the same thing everywhere. Operators: equals, not_equals, contains, not_contains, starts_with, ends_with, greater_than, greater_or_equal, less_than, less_or_equal, in_list, matches_regex, is_empty, is_not_empty, is_true, is_false. Add a new operator there and nowhere else.
- **Comparison rules.** Numbers and numeric strings compare by value (`"5"` equals `5`); everything else compares as exact text, so `"abc"` never equals `0` and `null` never equals `""`. `is_empty` is true only for null, blank text and an empty list: `0`, `"0"` and `false` are values. `contains` also searches a list. Ordering operators need two numbers, otherwise they are false.
- **Unknown operator = error.** A Filter or Router step throws, and a trigger condition holds the event back (`ignored`). It never quietly passes. Publishing rejects a trigger condition with an unknown operator, a missing path, a bad `source`, or a `matches_regex` pattern that doesn't compile or is over 200 characters (`GraphValidator::triggerFilterErrors()`). Patterns are run with our own delimiter and the `u` flag, so users can't add modifiers.
- **Router** needs no `value` for unary operators and falls back to the `default` branch when nothing matches.
- **Delay** is capped at 7 days (`DelayNode::MAX_SECONDS`) and clamps negatives to 0. Anything longer belongs on the Wait node, which resumes on a callback instead of holding a delayed queue job.
- **Call API** never sends a body on GET. Requests go through `GuardedHttp` (SSRF guard, pinned DNS, capped response, no header replay across origins).
- **Run Code** stays a whitelist (set, copy, uppercase, lowercase, concat). Real code execution still needs its own security review before it is built.
- **Triggers vs nodes.** Dedicated triggers (Schedule, Webhook, Polling) share the delivery controls from `TriggerNode::withActivateSwitch()`: skip-if-running, failure breaker, runs per minute, grouping, concurrency. Manual is retired and kept only so old workflows still load.

### Linear (37 nodes) and Jira (46 nodes)

Built to cover Gumloop's Linear Issue Reader/Writer and its Linear and Jira tool sets, plus Jira's Issue Reader/Writer/Updater (`docs/gumloop/output/raw/nodes/...`). Both connect with OAuth 2.0 (`linear`, `jira` connectors), poll for triggers, and have dropdowns (`LinearOptions`, `JiraOptions`).

| Family | Nodes |
|---|---|
| **Linear issues** | List, Search, Get, Create, Update, Delete; *List New Issues* and *List Updated Issues* (triggers) |
| **Linear relations** | List, Create (blocks, blocked by, related, duplicate), Update, Delete |
| **Linear comments** | List, Create (or reply), Update, Delete; *List New Comments* (trigger) |
| **Linear projects, initiatives, updates** | Projects: List, Get, Create, Update, Delete. Initiatives: List, Get, Create, Update (also links and unlinks projects), Delete. Project updates: List, Create, Update, Delete |
| **Linear lookups** | Teams, Statuses, Labels, Users, Cycles, Current user |
| **Jira projects** | List, Get, Create (from a template), Update, Delete |
| **Jira issues** | Create, Get, Update (one issue, or all a JQL query or saved filter finds), Delete, Transition, List Transitions, Search (JQL), List (fields, JQL or saved filter); *List New Issues* and *List Updated Issues* (triggers) |
| **Jira comments and files** | Comments: Add (internal for Service Management), List, Update, Delete. Attachments: Add, Download |
| **Jira metadata** | Fields, Edit metadata, Issue types, Priorities, Saved filters |
| **Jira people** | List users, Add user to issue (assignee, reporter, watcher), Current user, My issues, My recent activity, My permissions; Groups: List, Create, Add user, Remove user |
| **Jira links** | Link types, Create, Delete, Get an issue's links |
| **Jira Service Management** | Service desks, Request types, Request type fields, Create/Get/List customer requests |

Notes:

- **Names or ids.** A Linear field that takes a team, status, label, person or project accepts the id (what the dropdown fills in) or the name/key/email, resolved with one lookup (`AbstractLinearNode::resolveId()`): a value mapped from an earlier step works as it is, which is how Gumloop's writer is used. Jira's people fields accept an account id, an email or a display name (`resolveUser()`); a name that matches several people is an error rather than a guess.
- **Linear transport.** One GraphQL endpoint. Errors come back as HTTP 200 with `errors`, so the node checks both. A mutation that reports `success: false` is an error. `priority` takes Urgent, High, Medium, Low or No priority (or 0 to 4).
- **Jira transport.** Through Atlassian's gateway: the account's sites come from `accessible-resources` (cached five minutes) and every node has a `site` setting (id, address or name; empty is the first site). Searches use the enhanced `/search/jql` endpoint with page tokens, which refuses a query with no restriction, so *List Issues* asks for a project or another filter.
- **Rich text.** Jira's descriptions and comments are ADF. `JiraAdf` turns Markdown (headings, lists, quotes, code, bold, italic, links) into ADF going in and ADF into readable text coming out. Only web and mail links become links.
- **Custom fields in Jira** are given as text by name or id and converted from the project's own field list (createmeta on create, editmeta on update): numbers, single and multiple choices (comma-separated), parent > child choices, people, versions and components. `extra_fields` merges raw Jira JSON for anything else.
- **Jira update by query** is capped (`max_issues`, at most 100) and refuses to set labels outright and add/remove them at once.
- **Files.** Attachments move as text or base64 (10 MB cap), like the Gmail and Slack nodes.
- **To verify against the providers** (written from the public docs, not run against live accounts): Linear's `projectUpdateDelete`, `initiativeToProject*` and `projectStatuses` operations and its `scope` list, Jira's createmeta field-list shape, the project template keys, and the `/search/jql` and `/priority/search` endpoints.

### Messaging: Telegram (55), Discord (56), Twilio (23) and Email (4)

All four connect with the member's **own keys**, not a platform app, so there is nothing to register on our side. The connection form is `connectors.fields` (`Connector` rows `telegram`, `discord`, `twilio`, `smtp`): Telegram a bot token; Discord a bot token plus the application id and public key (only for slash command and button triggers); Twilio an account SID and auth token; SMTP host, port, encryption, login and From address. Because a login is more than one token, the nodes of these apps read the whole account with `ResolvesConnectorCredential::resolveCredentialData()` (`ConnectorCredentialResolver::credentialData()`), and dropdown sources get it as `NodeOptionsQuery::$credentialData`. The token field is always named `api_key`, so `ConnectorTokens::accessToken()` and the connection check keep working.

| Family | Nodes |
|---|---|
| **Telegram messages** | Send Message, Photo, Document, Video, Audio, Voice, Animation, Sticker, Album, Location, Venue, Contact, Poll (or quiz), Dice, Chat Action; Edit Text, Caption and Buttons; Delete (one or many); Forward, Copy; Pin, Unpin, Unpin All; Stop Poll; Answer Button Press |
| **Telegram chats and members** | Get Chat, Member Count, Chat Member, List Administrators; Leave; Set Title and Description; Create Invite Link; Ban, Unban, Mute or Unmute; Approve and Decline Join Request |
| **Telegram bot** | Get Bot, Get File (the download link, which holds the token, only when asked), Set, Get and Delete Commands, Get Webhook Status |
| **Telegram triggers** (instant, push-only) | New Message (chat, kind of chat, command, ignore bots), Message Edited, New Channel Post, Button Pressed (exact or prefix), Member Joined, Member Left, Poll Answered, Bot Added or Removed, Join Request |
| **Discord messages** | Send Message (embed, buttons, reply, safe pings), Send Direct Message, Send Webhook Message (no bot), Edit, Delete, Bulk Delete, Get, List, Pin, Unpin, List Pinned; Reactions: Add, Remove, List, Clear |
| **Discord channels and threads** | List, Get, Create, Edit, Delete channels; Create Thread (from a message, alone, or as a forum post), List Active Threads, Archive or Lock, Add and Remove Person |
| **Discord servers and people** | List Servers, Get Server, List, Get and Search Members, Set Nickname, Time Out; Roles: List, Create, Delete, Add to Member, Remove; Kick, Ban, Unban, List Bans; Invites: Create, List, Delete; Get User, Get Bot |
| **Discord slash commands** | Register, List, Delete Command; Respond to Slash Command or Button |
| **Discord triggers** | Instant: Slash Command Used, Button Clicked. Polled: New Message (every minute), New Thread, New Channel, New Member |
| **Twilio messages** | Send SMS (and MMS), Send WhatsApp (free text or approved template), Get, List, Delete, Redact |
| **Twilio calls** | Make Call (say, play, TwiML or a TwiML URL; record, machine detection), Get, List, End or Redirect, List and Delete Recordings |
| **Twilio account** | List Phone Numbers, Get Account, Get Balance, Look Up Phone Number (line type and caller name optional); Verify: List Services, Send Code, Check Code |
| **Twilio triggers** | Message Received (instant for a text to a named number; otherwise every minute; WhatsApp too), Message Failed, Call Received, Recording Ready |
| **Email (SMTP)** | Send Email (Cc, Bcc, Reply-To, HTML with a text twin, attachments from links), Send to Many (each separately, failures reported), Validate Address (shape, MX, free and throwaway providers), Parse Inbound Email |

Behaviour worth knowing:

- **Triggers are as instant as each provider allows, and the node says which.** Telegram never lists updates and a bot with a webhook can't be polled, so its triggers are push-only (`NeedsPushDelivery`: "Needs instant delivery" when it isn't working). Discord pushes messages, joins and threads only over a gateway connection this app doesn't keep, so those poll; slash commands and buttons arrive at the application's Interactions URL and are instant. Twilio texts to a named number are instant through the number's incoming-message URL; everything else polls.
- **Never take over what is in use.** A Telegram bot, a Discord application and a Twilio number each have room for one webhook. If it already points somewhere else the trigger is refused with a sentence naming where (`WatchRefusedException`), and the poll-based ones keep polling. Twilio's untouched demo URL is replaced, and the previous URL is put back when the watch goes. If someone replaces ours later, the daily check says so (reconnect needed).
- **Verification.** Telegram: a per-bot secret in `X-Telegram-Bot-Api-Secret-Token`, the bot id in the URL. Discord: Ed25519 over timestamp and body with the application's public key (the watch is saved before Discord's PING so the ingress finds the key), answered within three seconds with "thinking…"; the flow's *Respond* step fills it in through the interaction token within 15 minutes. Twilio: HMAC-SHA1 of the URL and sorted fields with the account's auth token, and the account SID must match; answered with empty TwiML. The generic `ProviderIngressController` gained `RepliesToDelivery` for the two that must answer in a particular shape.
- **Email in is not IMAP.** Reading a mailbox needs IMAP, which this server doesn't speak and which would need a new dependency. To start a flow from received mail, point an inbound email service (Mailgun routes, SendGrid Inbound Parse, Postmark inbound) at a Webhook trigger and follow it with *Parse Inbound Email*, which gives every service the same shape (`from_email`, `text`, `reply_text`, `attachments`, ...).
- **Checks before charges.** Phone numbers must be international (`+14155550123`), Telegram chats an id or `@username`, Discord ids numbers (a pasted `<#id>` or `<@id>` is accepted), and limits (message length, buttons, polls, bulk deletes) are checked before the call. Discord's `@everyone` and role pings are off unless asked for. A 429 from Discord waits once if it is short.
- **To verify against the providers** (written from the public docs, not run against live accounts): Telegram's `setWebhook` with `secret_token` and `allowed_updates`; Discord's `PATCH /applications/@me` accepting `interactions_endpoint_url` for a bot token, the PING check, the member-list and bans limits and the privileged intents; Twilio's `DateSent>` and `StartTime>` filters, `ContentSid` for WhatsApp templates, and the Lookup v2 `Fields`.

### Marketing: Typeform (17), Mailchimp (40), Kit (30), ActiveCampaign (29), Klaviyo (22), Brevo (20), SendGrid (20), Google Forms (8)

All connect with the member's **own API key** (`Connector` rows `typeform`, `mailchimp`, `kit`, `activecampaign`, `klaviyo`, `brevo`, `sendgrid`; Google Forms uses the Google connection). ActiveCampaign also asks for the account URL (only `*.api-us1.com`, `*.activehosted.com` and `api-xx.com` over https are accepted); Mailchimp finds its data center from the key's suffix; Typeform has an EU-data-center switch. Each has a connection check in `ConnectorCredentialTester`.

| Family | Nodes |
|---|---|
| **Typeform** | Forms: List, Get, Create, Update, Delete; Responses: List (answers by title, by ref, in order), Get, Delete; Webhooks: List, Create, Delete; Workspaces: List, Create, Rename, Delete; List Themes, Get Me. Trigger: New Response (instant) |
| **Mailchimp** | Audiences: List, Get, Create, Delete, Merge Fields; Members: Add or Update, Get, Update, Search, List, Archive, Delete, Unsubscribe, Tags (add, remove, list), Notes (add, list), Activity; Segments: List, Create; Campaigns: List, Get, Create, Set Content, Check Readiness, Send Test, Schedule, Unschedule, Send, Replicate, Delete, Report, Link Clicks; List Templates, Get Account. Triggers: New Subscriber, Unsubscribed, Cleaned, Profile Updated (instant), Campaign Sent (polled) |
| **Kit (ConvertKit)** | Subscribers: Create or Update, Get, List, Update, Unsubscribe; Tags: List, Create, Tag, Untag, List a person\'s tags; Forms and Sequences: List, Add a subscriber; Broadcasts: List, Get, Create, Update, Delete, Stats; Custom Fields: List, Create; Purchases: Create, List; List Email Templates, Get Account. Triggers: New Subscriber, Unsubscribed, Bounced, Tag Added, Form Subscribed, New Purchase (instant) |
| **ActiveCampaign** | Contacts: Sync (create or update), Get, List, Update, Delete; Lists: List, Create, Subscribe, Unsubscribe; Tags: List, Create, Add, Remove, List a contact\'s tags; Automations: List, Add to, Remove from; Deals: Create, Get, Update, Delete, List; Notes: Add, List; Pipelines, Custom Fields, Users, Get Account. Triggers: New Contact, Contact Updated, New Deal (instant) |
| **Klaviyo** | Profiles: Upsert, Get, Update, List; Lists: List, Create, Delete, Add or Remove a profile, List Profiles; Segments: List, List Profiles; Subscribe and Unsubscribe (consent), Track Event, List Events and Metrics; Flows: List, Set Status; List Campaigns, Templates, Get Account. Triggers (polled): New Profile, New Event (per metric) |
| **Brevo** | Contacts: Add or Update, Get, Update, List, Delete; Lists: List, Create, Add or Remove contacts (up to 150); Send Email (template or written, scheduled), Send SMS, Track Event, Campaigns: List, Send; Templates, Senders, Get Account. Triggers (polled): New Contact, Contact Updated, Campaign Sent |
| **SendGrid** | Send Email (written or dynamic template, one copy each, scheduled up to 72 h); Marketing contacts: Add or Update, Get, Search, Delete; Lists: List, Create, Delete, Remove a contact; Stats; Suppressions: five list nodes, Remove, Add Global Unsubscribe; Templates, Senders, Get Account. Triggers (polled): New Bounce, Block, Spam Report, Invalid Email, Unsubscribe, New Contact |
| **Google Forms** | Create, Get, Update Info, Add Question (text, paragraph, choice, checkboxes, dropdown, scale, date, time), Delete Question; List and Get Responses. Trigger: New Response |

Behaviour worth knowing:

- **Instant where the provider can register a webhook.** Typeform (a webhook per form, HMAC-SHA256 `Typeform-Signature`), Mailchimp (an audience webhook, token in the URL), Kit (subscriber and purchase webhooks, token in the URL) and ActiveCampaign (a webhook per event set, token in the URL) are *push that pokes the poll*: the delivery only makes the trigger's own poll look at once, so push and poll share the same filters, order and dedupe and cannot disagree. A 5-minute poll stays as a safety net. Klaviyo, Brevo and SendGrid have no webhook that can be registered per trigger through the API, so they poll only and say so.
- **Dedupe ids carry the time of the change where one person can repeat an event**: unsubscribe, bounce, tag added, contact updated, suppression. Someone who unsubscribes, rejoins and unsubscribes again starts two runs.
- **Never leave a half-registered hook.** Mailchimp, Kit and ActiveCampaign validate the URL when it is registered, so the watch is saved first and deleted again if the provider refuses.
- **Checks before sending.** Emails are validated, Brevo and SendGrid recipients and sizes capped (150 and 1000), a scheduled send must be in the future (and within 72 hours for SendGrid), Mailchimp\'s send checks readiness first, Klaviyo filter text is escaped, ActiveCampaign money is converted to cents. Subjects and names have line breaks removed.
- **Quirks handled.** SendGrid marketing writes are queued (`job_id`), so a delete or add says so; a contact that does not exist is `found: false`, not an error. Brevo answers 204 to an update of an existing contact, so *Add or Update* reads it back and says `created`.
- **To verify against the providers** (written from the public docs, not run against live accounts): Typeform\'s `PUT /forms/{id}/webhooks/{tag}` and the signature header; Mailchimp\'s webhook `sources` and form-encoded payload; Kit v4 webhook event names and `created_after` filters; ActiveCampaign\'s webhook `sources` and `events` names; Klaviyo revision `2024-10-15` filter syntax; Brevo\'s `modifiedSince` and campaign statistics fields; SendGrid\'s contact query (SGQL) timestamp format, the `search/emails` 404 for no match, and suppression list ordering.

### Triggers that keep state (`KeepsTriggerState`)

A reader node can start a flow by listing what's new (`SupportsTriggerMode`). When the event is something that *stopped* being there (a row deleted, a file trashed), the node also implements `KeepsTriggerState`: `pollSince($run, $config, $state)` returns the items and the state to keep, `triggerSampleItems()` gives example items for the editor. Use `ActsAsRemovalTrigger` plus `PresenceTracker` for "this id was there and now isn't"; use a provider's own change token (Drive) when it has one. Built: `google_sheets_list_deleted_rows`, `notion_list_deleted_database_items`, `google_drive_list_trashed_files`. Details and limits: `docs/APP_TRIGGERS_PLAN.md`.

## Data nodes (`Nodes/DataTransform/`)

Built (all extend `PureDataNode`: no network, files or database, `ActionEffect::Read`, so agents may call them freely; each returns `result` unless noted):

| Type | Class | What it does |
|---|---|---|
| `set_variable` | `SetVariableNode` | Names values for later steps (`variables` name → value, types kept). Output is one field per variable; names must be `[A-Za-z_][A-Za-z0-9_]*`. Replaces the old `VariableNode`/`SetVariableNode`. |
| `string` | `StringNode` ("Text") | trim, upper/lower/title case, slugify, length, find and replace, regex replace/extract, substring, truncate, split. |
| `json` | `JsonNode` | parse JSON text, stringify data, get a value by dot path with a default. |
| `array` | `ArrayNode` ("List") | length, first, last, join, unique, sort (by field, either way), reverse, slice, pluck a field, flatten. |
| `math` | `MathNode` | add, subtract, multiply, divide, remainder, power, round/floor/ceil/abs, and sum/average/min/max of a list. |
| `date_time` | `DateTimeNode` | now, reformat or change timezone, add/subtract time, time between two dates (also returns `timestamp`). |

Notes for these nodes:

- **Typing.** The engine casts a whole `{{ }}` value to the field's declared type (`ResolvedConfigCaster`), so a field that must receive data as it is (`json.value`) is declared without a `type`; a typed text field would turn a list into JSON text first.
- **Errors, not guesses.** Bad input (divide by zero, a result too large to hold, an unreadable date, a broken pattern, a list where text was expected) throws `InvalidArgumentException` with a sentence for the run log. Size caps: text 1,000,000 characters, JSON 5 MB, lists 100,000 items.
- **Patterns** use `ConditionEvaluator::regex()`: our delimiter and the `u` flag, 200 characters at most.
- **Dates** read a timezone-less value in the chosen timezone (UTC when empty) and print ISO 8601. Months and years can be added but not measured, because they vary in length.
- **Still to port:** `CacheNode` (needs a workspace-scoped store and a retention rule first), `DataNode` (undefined in the old project's notes: confirm what it did before building) and `LoggerNode` (decide where its lines are shown).

## AI nodes (`Nodes/AiAutomation/`)

`Apps/Ai/LlmNode` → `AskAiNode.php` (the "Ask AI" node from `docs/PLAN.md`'s Gumloop research notes), `Apps/OpenAi/{OpenAiChatCompletionNode,OpenAiImageGenerationNode,OpenAiEmbeddingsNode}` — ported as provider-specific nodes alongside the provider-agnostic `AskAiNode` (which should route through `laravel/ai`'s own provider abstraction rather than being OpenAI-specific — flag at build time whether the OpenAI-specific nodes are still needed once `AskAiNode` covers multi-provider, or whether they're kept for users who want to pin a specific provider node explicitly).

## Debug (`Nodes/DataTransform/` or a dedicated `Nodes/Debug/`)

`Apps/Debug/LoggerNode` — ported as-is.

## Integrations (`Nodes/Integrations/{Service}/`)

One row per old-project file; class name and behavior port unchanged, only the namespace/folder moves. Grouped by service, in the priority order given below.

**Priority 1 — build first** (already needed elsewhere in this project, or most commonly requested):
- **Slack**: `SlackPostMessageNode`, `SlackListChannelsNode`, `SlackCreateChannelNode`, `SlackInviteToChannelNode`, `SlackGetChannelHistoryNode`, `SlackUploadFileNode`, `SlackListUsersNode`
- **Google / Gmail** (largest single family — 30 nodes, split into subfolders `Integrations/Gmail/`, `Integrations/GoogleSheets/`, `Integrations/GoogleDrive/`, `Integrations/GoogleCalendar/` per `STRUCTURE.md`'s existing split): `GmailSendEmailNode`, `GmailListMessagesNode`, `GmailGetMessageNode`, `GmailReplyToMessageNode`, `GmailCreateDraftNode`, `GmailDeleteMessageNode`, `GmailModifyMessageNode`, `GmailAddLabelNode`, `GmailListLabelsNode`; `GoogleSheetsCreateSpreadsheetNode`, `GoogleSheetsGetSpreadsheetInfoNode`, `GoogleSheetsGetRowsNode`, `GoogleSheetsAppendRowNode`, `GoogleSheetsUpdateRowNode`, `GoogleSheetsDeleteRowsNode`, `GoogleSheetsLookupRowsNode`, `GoogleSheetsClearRangeNode`; `GoogleDriveListFilesNode`, `GoogleDriveGetFileNode`, `GoogleDriveUploadFileNode`, `GoogleDriveDownloadFileNode`, `GoogleDriveUpdateFileNode`, `GoogleDriveDeleteFileNode`, `GoogleDriveCreateFolderNode`, `GoogleDriveShareFileNode`; `GoogleCalendarListCalendarsNode`, `GoogleCalendarListEventsNode`, `GoogleCalendarGetEventNode`, `GoogleCalendarCreateEventNode`, `GoogleCalendarUpdateEventNode`, `GoogleCalendarDeleteEventNode`
- **GitHub**: `GitHubGetRepoNode`, `GitHubListReposNode`, `GitHubListIssuesNode`, `GitHubCreateIssueNode`, `GitHubCreateCommentNode`, `GitHubListPullRequestsNode`, `GitHubCreatePullRequestNode`, `GitHubListCommitsNode`
- **Stripe**: `StripeCreateCustomerNode`, `StripeRetrieveCustomerNode`, `StripeCreateProductNode`, `StripeCreatePriceNode`, `StripeCreateChargeNode`, `StripeCreateSubscriptionNode`, `StripeCancelSubscriptionNode`, `StripeListSubscriptionsNode`, `StripeCreateInvoiceNode`, `StripeListPaymentsNode`, `StripeGetBalanceNode`, `StripeNode` (generic/raw)
- **AWS S3**: `AwsS3PutObjectNode`, `AwsS3GetObjectNode`, `AwsS3ListObjectsNode`, `AwsS3DeleteObjectNode`, `AwsS3GetUrlNode`

**Priority 2 — common SaaS/dev tools**:
- **Notion**: `NotionCreatePageNode`, `NotionGetPageNode`, `NotionUpdatePageNode`, `NotionAppendBlocksNode`, `NotionQueryDatabaseNode`, `NotionListDatabasesNode`
- **Linear**: `LinearListIssuesNode`, `LinearCreateIssueNode`, `LinearUpdateIssueNode`, `LinearCreateCommentNode`
- **Jira**: `JiraNode`, `JiraGetIssueNode`, `JiraSearchIssuesNode`, `JiraCreateIssueNode`, `JiraUpdateIssueNode`, `JiraTransitionIssueNode`, `JiraAddCommentNode`
- **GitLab**: `GitLabListIssuesNode`, `GitLabCreateIssueNode`, `GitLabUpdateIssueNode`, `GitLabListMergeRequestsNode`, `GitLabCreateMergeRequestNode`, `GitLabListPipelinesNode`, `GitLabTriggerPipelineNode`
- **Trello**: `TrelloListCardsNode`, `TrelloCreateCardNode`, `TrelloUpdateCardNode`, `TrelloMoveCardNode`, `TrelloAddCommentNode`
- **HubSpot**: `HubspotListContactsNode`, `HubspotGetContactNode`, `HubspotCreateContactNode`, `HubspotSearchContactsNode`, `HubspotListCompaniesNode`, `HubspotCreateCompanyNode`, `HubspotListDealsNode`, `HubspotCreateDealNode`
- **Salesforce**: `SalesforceQueryNode`, `SalesforceGetRecordNode`, `SalesforceCreateRecordNode`, `SalesforceUpdateRecordNode`, `SalesforceDeleteRecordNode`
- **Airtable**: `AirtableListRecordsNode`, `AirtableGetRecordNode`, `AirtableCreateRecordNode`, `AirtableUpdateRecordNode`, `AirtableDeleteRecordNode`

**Priority 3 — messaging/comms**:
- **Discord**: `DiscordSendMessageNode`, `DiscordSendWebhookNode`, `DiscordCreateChannelNode`, `DiscordGetGuildMembersNode`
- **Telegram**: `TelegramSendMessageNode`, `TelegramSendPhotoNode`, `TelegramSendDocumentNode`, `TelegramEditMessageNode`, `TelegramDeleteMessageNode`, `TelegramGetUpdatesNode`
- **Twilio**: `TwilioSendSmsNode`, `TwilioSendWhatsappNode`, `TwilioMakeCallNode`, `TwilioSendVerificationNode`, `TwilioCheckVerificationNode`, `TwilioNode`
- **Mail (SMTP)**: `EmailSendNode`
- **SendGrid**: `SendgridSendEmailNode`, `SendgridSendTemplateNode`, `SendgridAddContactNode`, `SendgridListContactsNode`
- **Mailchimp**: `MailchimpAddSubscriberNode`, `MailchimpUpdateSubscriberNode`, `MailchimpRemoveSubscriberNode`, `MailchimpListSubscribersNode`, `MailchimpGetSubscriberNode`, `MailchimpAddTagNode`, `MailchimpListCampaignsNode`, `MailchimpListListsNode`

**Priority 4 — data stores**:
- **MySQL**: `MysqlSelectNode`, `MysqlInsertNode`, `MysqlUpdateNode`, `MysqlDeleteNode`, `MysqlRawQueryNode`
- **PostgreSQL**: `PostgresQueryNode`
- **MongoDB**: `MongodbFindNode`, `MongodbFindOneNode`, `MongodbInsertOneNode`, `MongodbInsertManyNode`, `MongodbUpdateOneNode`, `MongodbDeleteOneNode`
- **Redis**: `RedisGetNode`, `RedisSetNode`, `RedisDeleteNode`, `RedisKeysNode`, `RedisIncrementNode`, `RedisPublishNode`

**Priority 5 — long tail** (build on demand):
- **Dropbox**: `DropboxListFolderNode`, `DropboxGetLinkNode`, `DropboxCreateFolderNode`, `DropboxMoveFileNode`, `DropboxDeleteFileNode`
- **FTP**: `FtpListFilesNode`, `FtpUploadNode`, `FtpDownloadNode`, `FtpDeleteNode`
- **Twitter/X**: `TwitterPostTweetNode`, `TwitterSearchTweetsNode`, `TwitterGetUserNode`, `TwitterDeleteTweetNode`
- **Twitch**: `TwitchGetChannelInfoNode`, `TwitchGetStreamsNode`, `TwitchGetUserNode`

## Credential requirement

Every integration node above needs a `ConnectorCredential` (OAuth or API-key) resolved at execute time — see `docs/PLAN.md`'s Phase 6 ("Connectors/integrations") and `Services/Workflows/Nodes/Concerns/ResolvesCredentials.php` (old project, ported as-is: a trait every integration node uses to pull its workspace-scoped credential rather than each node hand-rolling credential lookup). No integration node above should be built before the `Connector`/`ConnectorCredential` models exist — build order is: Connector foundation → Slack/Google/GitHub/Stripe/AWS S3 (Priority 1) → remaining families as needed.

## Build order

Ship one integration family per PR, in the priority order above, each with: node classes, a feature test hitting a faked HTTP client (`Http::fake()`) or mocked SDK client, and a config-schema entry so the node picker (frontend, out of scope here) can render it. Do not attempt all ~150 in one PR — the old project itself was built incrementally, and the categories above are already sequenced for that.
