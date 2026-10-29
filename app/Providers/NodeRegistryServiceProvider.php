<?php

namespace App\Providers;

use App\Contracts\NodeContract;
use App\Nodes\AiAutomation\AgentNode;
use App\Nodes\AiAutomation\AskAiNode;
use App\Nodes\DataTransform\ArrayNode;
use App\Nodes\DataTransform\CallApiNode;
use App\Nodes\DataTransform\DateTimeNode;
use App\Nodes\DataTransform\JsonNode;
use App\Nodes\DataTransform\MathNode;
use App\Nodes\DataTransform\RunCodeNode;
use App\Nodes\DataTransform\SetVariableNode;
use App\Nodes\DataTransform\StringNode;
use App\Nodes\DataTransform\TransformNode;
use App\Nodes\FlowLogic\DelayNode;
use App\Nodes\FlowLogic\FilterNode;
use App\Nodes\FlowLogic\RouterNode;
use App\Nodes\InputsOutputs\WorkflowInputNode;
use App\Nodes\InputsOutputs\WorkflowOutputNode;
use App\Nodes\Integrations\GitHub\GitHubAddAssigneesNode;
use App\Nodes\Integrations\GitHub\GitHubAddLabelsNode;
use App\Nodes\Integrations\GitHub\GitHubCreateBranchNode;
use App\Nodes\Integrations\GitHub\GitHubCreateCommentNode;
use App\Nodes\Integrations\GitHub\GitHubCreateGistNode;
use App\Nodes\Integrations\GitHub\GitHubCreateIssueNode;
use App\Nodes\Integrations\GitHub\GitHubCreateOrUpdateFileNode;
use App\Nodes\Integrations\GitHub\GitHubCreatePullRequestNode;
use App\Nodes\Integrations\GitHub\GitHubCreatePullRequestReviewNode;
use App\Nodes\Integrations\GitHub\GitHubCreateReleaseNode;
use App\Nodes\Integrations\GitHub\GitHubCreateRepoNode;
use App\Nodes\Integrations\GitHub\GitHubDeleteBranchNode;
use App\Nodes\Integrations\GitHub\GitHubDeleteCommentNode;
use App\Nodes\Integrations\GitHub\GitHubDeleteFileNode;
use App\Nodes\Integrations\GitHub\GitHubDispatchWorkflowNode;
use App\Nodes\Integrations\GitHub\GitHubForkRepositoryNode;
use App\Nodes\Integrations\GitHub\GitHubGetCommitNode;
use App\Nodes\Integrations\GitHub\GitHubGetFileContentNode;
use App\Nodes\Integrations\GitHub\GitHubGetIssueNode;
use App\Nodes\Integrations\GitHub\GitHubGetLatestReleaseNode;
use App\Nodes\Integrations\GitHub\GitHubGetPullRequestNode;
use App\Nodes\Integrations\GitHub\GitHubGetRepoNode;
use App\Nodes\Integrations\GitHub\GitHubGetUserNode;
use App\Nodes\Integrations\GitHub\GitHubListBranchesNode;
use App\Nodes\Integrations\GitHub\GitHubListClosedIssuesNode;
use App\Nodes\Integrations\GitHub\GitHubListCommitsNode;
use App\Nodes\Integrations\GitHub\GitHubListForksNode;
use App\Nodes\Integrations\GitHub\GitHubListIssueCommentsNode;
use App\Nodes\Integrations\GitHub\GitHubListIssuesNode;
use App\Nodes\Integrations\GitHub\GitHubListMergedPullRequestsNode;
use App\Nodes\Integrations\GitHub\GitHubListNotificationsNode;
use App\Nodes\Integrations\GitHub\GitHubListPullRequestFilesNode;
use App\Nodes\Integrations\GitHub\GitHubListPullRequestsNode;
use App\Nodes\Integrations\GitHub\GitHubListReleasesNode;
use App\Nodes\Integrations\GitHub\GitHubListRepoCommentsNode;
use App\Nodes\Integrations\GitHub\GitHubListReposNode;
use App\Nodes\Integrations\GitHub\GitHubListStargazersNode;
use App\Nodes\Integrations\GitHub\GitHubListWorkflowRunsNode;
use App\Nodes\Integrations\GitHub\GitHubMergePullRequestNode;
use App\Nodes\Integrations\GitHub\GitHubRemoveLabelNode;
use App\Nodes\Integrations\GitHub\GitHubRequestReviewersNode;
use App\Nodes\Integrations\GitHub\GitHubRerunWorkflowRunNode;
use App\Nodes\Integrations\GitHub\GitHubSearchIssuesNode;
use App\Nodes\Integrations\GitHub\GitHubSearchRepositoriesNode;
use App\Nodes\Integrations\GitHub\GitHubStarRepositoryNode;
use App\Nodes\Integrations\GitHub\GitHubUnstarRepositoryNode;
use App\Nodes\Integrations\GitHub\GitHubUpdateCommentNode;
use App\Nodes\Integrations\GitHub\GitHubUpdateIssueNode;
use App\Nodes\Integrations\GitHub\GitHubUpdatePullRequestNode;
use App\Nodes\Integrations\Gmail\GmailAddLabelNode;
use App\Nodes\Integrations\Gmail\GmailCreateDraftNode;
use App\Nodes\Integrations\Gmail\GmailCreateLabelNode;
use App\Nodes\Integrations\Gmail\GmailDeleteMessageNode;
use App\Nodes\Integrations\Gmail\GmailGetAttachmentNode;
use App\Nodes\Integrations\Gmail\GmailGetMessageNode;
use App\Nodes\Integrations\Gmail\GmailGetThreadNode;
use App\Nodes\Integrations\Gmail\GmailListLabelsNode;
use App\Nodes\Integrations\Gmail\GmailListMessagesNode;
use App\Nodes\Integrations\Gmail\GmailListMessagesWithAttachmentsNode;
use App\Nodes\Integrations\Gmail\GmailModifyMessageNode;
use App\Nodes\Integrations\Gmail\GmailReplyToMessageNode;
use App\Nodes\Integrations\Gmail\GmailSendDraftNode;
use App\Nodes\Integrations\Gmail\GmailSendEmailNode;
use App\Nodes\Integrations\GoogleAnalytics\GoogleAnalyticsListPropertiesNode;
use App\Nodes\Integrations\GoogleAnalytics\GoogleAnalyticsRunRealtimeReportNode;
use App\Nodes\Integrations\GoogleAnalytics\GoogleAnalyticsRunReportNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarCheckAvailabilityNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarCreateEventNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarDeleteEventNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarGetEventNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarListCalendarsNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarListCancelledEventsNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarListEventsNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarListUpcomingEventsNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarListUpdatedEventsNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarQuickAddEventNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarUpdateEventNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsCreateContactNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsDeleteContactNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsGetContactNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsListContactsNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsListUpdatedContactsNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsSearchContactsNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsUpdateContactNode;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsAppendTextNode;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsCreateDocumentNode;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsGetDocumentNode;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsReadTextNode;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsReplaceTextNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveCopyFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveCreateFolderNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveDeleteFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveExportFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveGetFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveListFilesNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveListTrashedFilesNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveListUpdatedFilesNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveMoveFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveRenameFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveShareFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveUploadFileNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsCreateFormNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsGetFormNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsGetResponseNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsListResponsesNode;
use App\Nodes\Integrations\GoogleSearchConsole\GoogleSearchConsoleInspectUrlNode;
use App\Nodes\Integrations\GoogleSearchConsole\GoogleSearchConsoleListSitemapsNode;
use App\Nodes\Integrations\GoogleSearchConsole\GoogleSearchConsoleListSitesNode;
use App\Nodes\Integrations\GoogleSearchConsole\GoogleSearchConsoleQuerySearchAnalyticsNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsAddSheetNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsAppendValuesNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsClearRangeNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsCreateSpreadsheetNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsDeleteRowsNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsGetSpreadsheetInfoNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsGetValuesNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsListDeletedRowsNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsListUpdatedRowsNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsLookupRowsNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsUpdateValuesNode;
use App\Nodes\Integrations\GoogleSlides\GoogleSlidesAddSlideNode;
use App\Nodes\Integrations\GoogleSlides\GoogleSlidesCreatePresentationNode;
use App\Nodes\Integrations\GoogleSlides\GoogleSlidesGetPresentationNode;
use App\Nodes\Integrations\GoogleSlides\GoogleSlidesReplaceTextNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksCreateTaskListNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksCreateTaskNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksDeleteTaskNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksListCompletedTasksNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksListDueSoonTasksNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksListTaskListsNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksListTasksNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksUpdateTaskNode;
use App\Nodes\Integrations\Notion\NotionAppendBlocksNode;
use App\Nodes\Integrations\Notion\NotionArchivePageNode;
use App\Nodes\Integrations\Notion\NotionCreateCommentNode;
use App\Nodes\Integrations\Notion\NotionCreateDatabaseItemNode;
use App\Nodes\Integrations\Notion\NotionCreateDatabaseNode;
use App\Nodes\Integrations\Notion\NotionCreatePageNode;
use App\Nodes\Integrations\Notion\NotionDeleteBlockNode;
use App\Nodes\Integrations\Notion\NotionFindDatabaseItemsNode;
use App\Nodes\Integrations\Notion\NotionGetBlockNode;
use App\Nodes\Integrations\Notion\NotionGetDatabaseNode;
use App\Nodes\Integrations\Notion\NotionGetMeNode;
use App\Nodes\Integrations\Notion\NotionGetPageNode;
use App\Nodes\Integrations\Notion\NotionGetUserNode;
use App\Nodes\Integrations\Notion\NotionListBlockChildrenNode;
use App\Nodes\Integrations\Notion\NotionListCommentsNode;
use App\Nodes\Integrations\Notion\NotionListNewCommentsNode;
use App\Nodes\Integrations\Notion\NotionListNewDatabaseItemsNode;
use App\Nodes\Integrations\Notion\NotionListNewDatabasesNode;
use App\Nodes\Integrations\Notion\NotionListNewPagesNode;
use App\Nodes\Integrations\Notion\NotionListNewUsersNode;
use App\Nodes\Integrations\Notion\NotionListDeletedDatabaseItemsNode;
use App\Nodes\Integrations\Notion\NotionListUpdatedDatabaseItemsNode;
use App\Nodes\Integrations\Notion\NotionListUpdatedPagesNode;
use App\Nodes\Integrations\Notion\NotionListUsersNode;
use App\Nodes\Integrations\Notion\NotionQueryDatabaseNode;
use App\Nodes\Integrations\Notion\NotionRestorePageNode;
use App\Nodes\Integrations\Notion\NotionSearchNode;
use App\Nodes\Integrations\Notion\NotionUpdateBlockNode;
use App\Nodes\Integrations\Notion\NotionUpdateDatabaseNode;
use App\Nodes\Integrations\Notion\NotionUpdatePageNode;
use App\Nodes\Integrations\Outlook\OutlookCreateDraftNode;
use App\Nodes\Integrations\Outlook\OutlookCreateEventNode;
use App\Nodes\Integrations\Outlook\OutlookDeleteEventNode;
use App\Nodes\Integrations\Outlook\OutlookDeleteMessageNode;
use App\Nodes\Integrations\Outlook\OutlookGetMessageNode;
use App\Nodes\Integrations\Outlook\OutlookListCancelledEventsNode;
use App\Nodes\Integrations\Outlook\OutlookListEventsNode;
use App\Nodes\Integrations\Outlook\OutlookListMessagesNode;
use App\Nodes\Integrations\Outlook\OutlookListNewEventsNode;
use App\Nodes\Integrations\Outlook\OutlookListUpcomingEventsNode;
use App\Nodes\Integrations\Outlook\OutlookListUpdatedEventsNode;
use App\Nodes\Integrations\Outlook\OutlookMoveMessageNode;
use App\Nodes\Integrations\Outlook\OutlookReplyToMessageNode;
use App\Nodes\Integrations\Outlook\OutlookSendEmailNode;
use App\Nodes\Integrations\Slack\SlackAddReactionNode;
use App\Nodes\Integrations\Slack\SlackArchiveChannelNode;
use App\Nodes\Integrations\Slack\SlackCreateChannelNode;
use App\Nodes\Integrations\Slack\SlackDeleteMessageNode;
use App\Nodes\Integrations\Slack\SlackFindUserByEmailNode;
use App\Nodes\Integrations\Slack\SlackGetChannelHistoryNode;
use App\Nodes\Integrations\Slack\SlackGetChannelInfoNode;
use App\Nodes\Integrations\Slack\SlackGetMessageLinkNode;
use App\Nodes\Integrations\Slack\SlackGetThreadRepliesNode;
use App\Nodes\Integrations\Slack\SlackGetUserNode;
use App\Nodes\Integrations\Slack\SlackInviteToChannelNode;
use App\Nodes\Integrations\Slack\SlackJoinChannelNode;
use App\Nodes\Integrations\Slack\SlackListChannelJoinsNode;
use App\Nodes\Integrations\Slack\SlackListChannelsNode;
use App\Nodes\Integrations\Slack\SlackListDirectMessagesNode;
use App\Nodes\Integrations\Slack\SlackListFilesNode;
use App\Nodes\Integrations\Slack\SlackListMentionsNode;
use App\Nodes\Integrations\Slack\SlackListNewChannelsNode;
use App\Nodes\Integrations\Slack\SlackListNewPinsNode;
use App\Nodes\Integrations\Slack\SlackListNewUsersNode;
use App\Nodes\Integrations\Slack\SlackListUsersNode;
use App\Nodes\Integrations\Slack\SlackPinMessageNode;
use App\Nodes\Integrations\Slack\SlackPostMessageNode;
use App\Nodes\Integrations\Slack\SlackReactionAddedNode;
use App\Nodes\Integrations\Slack\SlackReactionRemovedNode;
use App\Nodes\Integrations\Slack\SlackRemoveFromChannelNode;
use App\Nodes\Integrations\Slack\SlackRemoveReactionNode;
use App\Nodes\Integrations\Slack\SlackRenameChannelNode;
use App\Nodes\Integrations\Slack\SlackScheduleMessageNode;
use App\Nodes\Integrations\Slack\SlackSendDirectMessageNode;
use App\Nodes\Integrations\Slack\SlackSendEphemeralMessageNode;
use App\Nodes\Integrations\Slack\SlackSetChannelPurposeNode;
use App\Nodes\Integrations\Slack\SlackSetChannelTopicNode;
use App\Nodes\Integrations\Slack\SlackUnpinMessageNode;
use App\Nodes\Integrations\Slack\SlackUpdateMessageNode;
use App\Nodes\Integrations\Slack\SlackUploadFileNode;
use App\Nodes\Integrations\YouTube\YouTubeGetChannelNode;
use App\Nodes\Integrations\YouTube\YouTubeGetVideoNode;
use App\Nodes\Integrations\YouTube\YouTubeListCommentsNode;
use App\Nodes\Integrations\YouTube\YouTubeListPlaylistVideosNode;
use App\Nodes\Integrations\YouTube\YouTubeSearchVideosNode;
use App\Nodes\TriggersEvents\ManualTriggerNode;
use App\Nodes\TriggersEvents\PollingTriggerNode;
use App\Nodes\TriggersEvents\ScheduleTriggerNode;
use App\Nodes\TriggersEvents\WebhookTriggerNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\ServiceProvider;

class NodeRegistryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NodeRegistry::class, function () {
            $registry = new NodeRegistry;

            foreach ($this->builtins() as $type => $class) {
                $registry->register($type, $class);
            }

            return $registry;
        });
    }

    /**
     * Type strings are registered as literals here rather than instantiated
     * and read via `NodeContract::type()` - `AgentNode`'s constructor needs
     * `AgentRunner` → `ToolRegistry` → `NodeRegistry`, so eagerly building
     * an instance while `NodeRegistry` itself is still being constructed is
     * a real circular dependency (infinite recursion, not just untidy).
     * Every type string here must still match its class's own `type()`
     * return value exactly: nothing enforces that at compile time, so
     * double-check it when adding a node.
     *
     * @return array<string, class-string<NodeContract>>
     */
    private function builtins(): array
    {
        return [
            'manual_trigger' => ManualTriggerNode::class,
            'schedule_trigger' => ScheduleTriggerNode::class,
            'webhook_trigger' => WebhookTriggerNode::class,
            'polling_trigger' => PollingTriggerNode::class,
            'workflow_input' => WorkflowInputNode::class,
            'workflow_output' => WorkflowOutputNode::class,
            'transform' => TransformNode::class,
            'call_api' => CallApiNode::class,
            'run_code' => RunCodeNode::class,
            'set_variable' => SetVariableNode::class,
            'string' => StringNode::class,
            'json' => JsonNode::class,
            'array' => ArrayNode::class,
            'math' => MathNode::class,
            'date_time' => DateTimeNode::class,
            'router' => RouterNode::class,
            'filter' => FilterNode::class,
            'ask_ai' => AskAiNode::class,
            'delay' => DelayNode::class,
            'agent' => AgentNode::class,
            'notion_append_blocks' => NotionAppendBlocksNode::class,
            'notion_archive_page' => NotionArchivePageNode::class,
            'notion_create_comment' => NotionCreateCommentNode::class,
            'notion_create_database_item' => NotionCreateDatabaseItemNode::class,
            'notion_create_database' => NotionCreateDatabaseNode::class,
            'notion_create_page' => NotionCreatePageNode::class,
            'notion_delete_block' => NotionDeleteBlockNode::class,
            'notion_find_database_items' => NotionFindDatabaseItemsNode::class,
            'notion_get_block' => NotionGetBlockNode::class,
            'notion_get_database' => NotionGetDatabaseNode::class,
            'notion_get_me' => NotionGetMeNode::class,
            'notion_get_page' => NotionGetPageNode::class,
            'notion_get_user' => NotionGetUserNode::class,
            'notion_list_block_children' => NotionListBlockChildrenNode::class,
            'notion_list_comments' => NotionListCommentsNode::class,
            'notion_list_new_comments' => NotionListNewCommentsNode::class,
            'notion_list_new_database_items' => NotionListNewDatabaseItemsNode::class,
            'notion_list_new_databases' => NotionListNewDatabasesNode::class,
            'notion_list_new_pages' => NotionListNewPagesNode::class,
            'notion_list_new_users' => NotionListNewUsersNode::class,
            'notion_list_updated_database_items' => NotionListUpdatedDatabaseItemsNode::class,
            'notion_list_deleted_database_items' => NotionListDeletedDatabaseItemsNode::class,
            'notion_list_updated_pages' => NotionListUpdatedPagesNode::class,
            'notion_list_users' => NotionListUsersNode::class,
            'notion_query_database' => NotionQueryDatabaseNode::class,
            'notion_restore_page' => NotionRestorePageNode::class,
            'notion_search' => NotionSearchNode::class,
            'notion_update_block' => NotionUpdateBlockNode::class,
            'notion_update_database' => NotionUpdateDatabaseNode::class,
            'notion_update_page' => NotionUpdatePageNode::class,
            'slack_reaction_removed' => SlackReactionRemovedNode::class,
            'slack_list_new_pins' => SlackListNewPinsNode::class,
            'slack_list_direct_messages' => SlackListDirectMessagesNode::class,
            'slack_post_message' => SlackPostMessageNode::class,
            'slack_list_channels' => SlackListChannelsNode::class,
            'slack_create_channel' => SlackCreateChannelNode::class,
            'slack_invite_to_channel' => SlackInviteToChannelNode::class,
            'slack_get_channel_history' => SlackGetChannelHistoryNode::class,
            'slack_reaction_added' => SlackReactionAddedNode::class,
            'slack_upload_file' => SlackUploadFileNode::class,
            'slack_list_users' => SlackListUsersNode::class,
            'slack_add_reaction' => SlackAddReactionNode::class,
            'slack_archive_channel' => SlackArchiveChannelNode::class,
            'slack_delete_message' => SlackDeleteMessageNode::class,
            'slack_find_user_by_email' => SlackFindUserByEmailNode::class,
            'slack_get_channel_info' => SlackGetChannelInfoNode::class,
            'slack_get_message_link' => SlackGetMessageLinkNode::class,
            'slack_get_thread_replies' => SlackGetThreadRepliesNode::class,
            'slack_get_user' => SlackGetUserNode::class,
            'slack_join_channel' => SlackJoinChannelNode::class,
            'slack_list_channel_joins' => SlackListChannelJoinsNode::class,
            'slack_list_files' => SlackListFilesNode::class,
            'slack_list_mentions' => SlackListMentionsNode::class,
            'slack_list_new_channels' => SlackListNewChannelsNode::class,
            'slack_list_new_users' => SlackListNewUsersNode::class,
            'slack_pin_message' => SlackPinMessageNode::class,
            'slack_remove_from_channel' => SlackRemoveFromChannelNode::class,
            'slack_remove_reaction' => SlackRemoveReactionNode::class,
            'slack_rename_channel' => SlackRenameChannelNode::class,
            'slack_schedule_message' => SlackScheduleMessageNode::class,
            'slack_send_direct_message' => SlackSendDirectMessageNode::class,
            'slack_send_ephemeral_message' => SlackSendEphemeralMessageNode::class,
            'slack_set_channel_purpose' => SlackSetChannelPurposeNode::class,
            'slack_set_channel_topic' => SlackSetChannelTopicNode::class,
            'slack_unpin_message' => SlackUnpinMessageNode::class,
            'slack_update_message' => SlackUpdateMessageNode::class,
            'gmail_send_email' => GmailSendEmailNode::class,
            'gmail_list_messages' => GmailListMessagesNode::class,
            'gmail_list_messages_with_attachments' => GmailListMessagesWithAttachmentsNode::class,
            'gmail_get_message' => GmailGetMessageNode::class,
            'gmail_reply_to_message' => GmailReplyToMessageNode::class,
            'gmail_create_draft' => GmailCreateDraftNode::class,
            'gmail_delete_message' => GmailDeleteMessageNode::class,
            'gmail_modify_message' => GmailModifyMessageNode::class,
            'gmail_add_label' => GmailAddLabelNode::class,
            'gmail_list_labels' => GmailListLabelsNode::class,
            'gmail_create_label' => GmailCreateLabelNode::class,
            'gmail_get_attachment' => GmailGetAttachmentNode::class,
            'gmail_get_thread' => GmailGetThreadNode::class,
            'gmail_send_draft' => GmailSendDraftNode::class,
            'github_get_repo' => GitHubGetRepoNode::class,
            'github_list_repos' => GitHubListReposNode::class,
            'github_create_repo' => GitHubCreateRepoNode::class,
            'github_list_issues' => GitHubListIssuesNode::class,
            'github_create_issue' => GitHubCreateIssueNode::class,
            'github_create_comment' => GitHubCreateCommentNode::class,
            'github_list_pull_requests' => GitHubListPullRequestsNode::class,
            'github_create_pull_request' => GitHubCreatePullRequestNode::class,
            'github_list_commits' => GitHubListCommitsNode::class,
            'github_add_assignees' => GitHubAddAssigneesNode::class,
            'github_add_labels' => GitHubAddLabelsNode::class,
            'github_create_branch' => GitHubCreateBranchNode::class,
            'github_create_gist' => GitHubCreateGistNode::class,
            'github_create_or_update_file' => GitHubCreateOrUpdateFileNode::class,
            'github_create_pull_request_review' => GitHubCreatePullRequestReviewNode::class,
            'github_create_release' => GitHubCreateReleaseNode::class,
            'github_delete_branch' => GitHubDeleteBranchNode::class,
            'github_delete_comment' => GitHubDeleteCommentNode::class,
            'github_delete_file' => GitHubDeleteFileNode::class,
            'github_dispatch_workflow' => GitHubDispatchWorkflowNode::class,
            'github_fork_repository' => GitHubForkRepositoryNode::class,
            'github_get_commit' => GitHubGetCommitNode::class,
            'github_get_file_content' => GitHubGetFileContentNode::class,
            'github_get_issue' => GitHubGetIssueNode::class,
            'github_get_latest_release' => GitHubGetLatestReleaseNode::class,
            'github_get_pull_request' => GitHubGetPullRequestNode::class,
            'github_get_user' => GitHubGetUserNode::class,
            'github_list_branches' => GitHubListBranchesNode::class,
            'github_list_closed_issues' => GitHubListClosedIssuesNode::class,
            'github_list_forks' => GitHubListForksNode::class,
            'github_list_issue_comments' => GitHubListIssueCommentsNode::class,
            'github_list_merged_pull_requests' => GitHubListMergedPullRequestsNode::class,
            'github_list_notifications' => GitHubListNotificationsNode::class,
            'github_list_pull_request_files' => GitHubListPullRequestFilesNode::class,
            'github_list_releases' => GitHubListReleasesNode::class,
            'github_list_repo_comments' => GitHubListRepoCommentsNode::class,
            'github_list_stargazers' => GitHubListStargazersNode::class,
            'github_list_workflow_runs' => GitHubListWorkflowRunsNode::class,
            'github_merge_pull_request' => GitHubMergePullRequestNode::class,
            'github_remove_label' => GitHubRemoveLabelNode::class,
            'github_request_reviewers' => GitHubRequestReviewersNode::class,
            'github_rerun_workflow_run' => GitHubRerunWorkflowRunNode::class,
            'github_search_issues' => GitHubSearchIssuesNode::class,
            'github_search_repositories' => GitHubSearchRepositoriesNode::class,
            'github_star_repository' => GitHubStarRepositoryNode::class,
            'github_unstar_repository' => GitHubUnstarRepositoryNode::class,
            'github_update_comment' => GitHubUpdateCommentNode::class,
            'github_update_issue' => GitHubUpdateIssueNode::class,
            'github_update_pull_request' => GitHubUpdatePullRequestNode::class,
            'google_drive_list_files' => GoogleDriveListFilesNode::class,
            'google_drive_list_updated_files' => GoogleDriveListUpdatedFilesNode::class,
            'google_drive_list_trashed_files' => GoogleDriveListTrashedFilesNode::class,
            'google_drive_get_file' => GoogleDriveGetFileNode::class,
            'google_drive_delete_file' => GoogleDriveDeleteFileNode::class,
            'google_drive_export_file' => GoogleDriveExportFileNode::class,
            'google_drive_copy_file' => GoogleDriveCopyFileNode::class,
            'google_drive_create_folder' => GoogleDriveCreateFolderNode::class,
            'google_drive_move_file' => GoogleDriveMoveFileNode::class,
            'google_drive_rename_file' => GoogleDriveRenameFileNode::class,
            'google_drive_share_file' => GoogleDriveShareFileNode::class,
            'google_drive_upload_file' => GoogleDriveUploadFileNode::class,
            'google_sheets_get_values' => GoogleSheetsGetValuesNode::class,
            'google_sheets_append_values' => GoogleSheetsAppendValuesNode::class,
            'google_sheets_update_values' => GoogleSheetsUpdateValuesNode::class,
            'google_sheets_add_sheet' => GoogleSheetsAddSheetNode::class,
            'google_sheets_clear_range' => GoogleSheetsClearRangeNode::class,
            'google_sheets_create_spreadsheet' => GoogleSheetsCreateSpreadsheetNode::class,
            'google_sheets_delete_rows' => GoogleSheetsDeleteRowsNode::class,
            'google_sheets_get_spreadsheet_info' => GoogleSheetsGetSpreadsheetInfoNode::class,
            'google_sheets_list_updated_rows' => GoogleSheetsListUpdatedRowsNode::class,
            'google_sheets_list_deleted_rows' => GoogleSheetsListDeletedRowsNode::class,
            'google_sheets_lookup_rows' => GoogleSheetsLookupRowsNode::class,
            'google_docs_create_document' => GoogleDocsCreateDocumentNode::class,
            'google_docs_get_document' => GoogleDocsGetDocumentNode::class,
            'google_docs_append_text' => GoogleDocsAppendTextNode::class,
            'google_docs_read_text' => GoogleDocsReadTextNode::class,
            'google_docs_replace_text' => GoogleDocsReplaceTextNode::class,
            'google_calendar_list_events' => GoogleCalendarListEventsNode::class,
            'google_calendar_list_cancelled_events' => GoogleCalendarListCancelledEventsNode::class,
            'google_calendar_list_upcoming_events' => GoogleCalendarListUpcomingEventsNode::class,
            'google_calendar_list_updated_events' => GoogleCalendarListUpdatedEventsNode::class,
            'google_calendar_create_event' => GoogleCalendarCreateEventNode::class,
            'google_calendar_delete_event' => GoogleCalendarDeleteEventNode::class,
            'google_calendar_check_availability' => GoogleCalendarCheckAvailabilityNode::class,
            'google_calendar_get_event' => GoogleCalendarGetEventNode::class,
            'google_calendar_list_calendars' => GoogleCalendarListCalendarsNode::class,
            'google_calendar_quick_add_event' => GoogleCalendarQuickAddEventNode::class,
            'google_calendar_update_event' => GoogleCalendarUpdateEventNode::class,
            'google_tasks_list_task_lists' => GoogleTasksListTaskListsNode::class,
            'google_tasks_create_task_list' => GoogleTasksCreateTaskListNode::class,
            'google_tasks_list_tasks' => GoogleTasksListTasksNode::class,
            'google_tasks_list_completed_tasks' => GoogleTasksListCompletedTasksNode::class,
            'google_tasks_list_due_soon_tasks' => GoogleTasksListDueSoonTasksNode::class,
            'google_tasks_create_task' => GoogleTasksCreateTaskNode::class,
            'google_tasks_update_task' => GoogleTasksUpdateTaskNode::class,
            'google_tasks_delete_task' => GoogleTasksDeleteTaskNode::class,
            'google_contacts_list_contacts' => GoogleContactsListContactsNode::class,
            'google_contacts_list_updated_contacts' => GoogleContactsListUpdatedContactsNode::class,
            'google_contacts_search_contacts' => GoogleContactsSearchContactsNode::class,
            'google_contacts_get_contact' => GoogleContactsGetContactNode::class,
            'google_contacts_create_contact' => GoogleContactsCreateContactNode::class,
            'google_contacts_update_contact' => GoogleContactsUpdateContactNode::class,
            'google_contacts_delete_contact' => GoogleContactsDeleteContactNode::class,
            'google_forms_get_form' => GoogleFormsGetFormNode::class,
            'google_forms_list_responses' => GoogleFormsListResponsesNode::class,
            'google_forms_get_response' => GoogleFormsGetResponseNode::class,
            'google_forms_create_form' => GoogleFormsCreateFormNode::class,
            'google_slides_create_presentation' => GoogleSlidesCreatePresentationNode::class,
            'google_slides_get_presentation' => GoogleSlidesGetPresentationNode::class,
            'google_slides_replace_text' => GoogleSlidesReplaceTextNode::class,
            'google_slides_add_slide' => GoogleSlidesAddSlideNode::class,
            'google_youtube_search_videos' => YouTubeSearchVideosNode::class,
            'google_youtube_get_video' => YouTubeGetVideoNode::class,
            'google_youtube_get_channel' => YouTubeGetChannelNode::class,
            'google_youtube_list_playlist_videos' => YouTubeListPlaylistVideosNode::class,
            'google_youtube_list_comments' => YouTubeListCommentsNode::class,
            'google_analytics_run_report' => GoogleAnalyticsRunReportNode::class,
            'google_analytics_run_realtime_report' => GoogleAnalyticsRunRealtimeReportNode::class,
            'google_analytics_list_properties' => GoogleAnalyticsListPropertiesNode::class,
            'google_search_console_list_sites' => GoogleSearchConsoleListSitesNode::class,
            'google_search_console_query_search_analytics' => GoogleSearchConsoleQuerySearchAnalyticsNode::class,
            'google_search_console_list_sitemaps' => GoogleSearchConsoleListSitemapsNode::class,
            'google_search_console_inspect_url' => GoogleSearchConsoleInspectUrlNode::class,
            'outlook_list_messages' => OutlookListMessagesNode::class,
            'outlook_get_message' => OutlookGetMessageNode::class,
            'outlook_send_email' => OutlookSendEmailNode::class,
            'outlook_reply_to_message' => OutlookReplyToMessageNode::class,
            'outlook_create_draft' => OutlookCreateDraftNode::class,
            'outlook_move_message' => OutlookMoveMessageNode::class,
            'outlook_delete_message' => OutlookDeleteMessageNode::class,
            'outlook_list_events' => OutlookListEventsNode::class,
            'outlook_list_new_events' => OutlookListNewEventsNode::class,
            'outlook_list_updated_events' => OutlookListUpdatedEventsNode::class,
            'outlook_list_cancelled_events' => OutlookListCancelledEventsNode::class,
            'outlook_list_upcoming_events' => OutlookListUpcomingEventsNode::class,
            'outlook_create_event' => OutlookCreateEventNode::class,
            'outlook_delete_event' => OutlookDeleteEventNode::class,
        ];
    }
}
