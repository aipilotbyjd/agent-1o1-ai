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

## Data nodes (`Nodes/DataTransform/`)

`CacheNode`, `StringNode`, `DataNode`, `JsonNode`, `DateTimeNode`, `ArrayNode`, `VariableNode`, `MathNode` — ported from `Apps/Data/*`, one class each, same names.

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
