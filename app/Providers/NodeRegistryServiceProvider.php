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
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignAddNoteNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignAddTagNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignAddToAutomationNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignCreateDealNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignCreateListNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignCreateTagNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignDeleteContactNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignDeleteDealNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignGetAccountNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignGetContactNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignGetDealNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListAutomationsNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListContactNotesNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListContactsNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListContactTagsNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListCustomFieldsNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListDealsNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListListsNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListPipelinesNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListTagsNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListUpdatedContactsNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignListUsersNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignRemoveFromAutomationNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignRemoveTagNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignSubscribeToListNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignSyncContactNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignUnsubscribeFromListNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignUpdateContactNode;
use App\Nodes\Integrations\ActiveCampaign\ActiveCampaignUpdateDealNode;
use App\Nodes\Integrations\Airtable\AirtableAddCommentNode;
use App\Nodes\Integrations\Airtable\AirtableCreateRecordNode;
use App\Nodes\Integrations\Airtable\AirtableCreateRecordsNode;
use App\Nodes\Integrations\Airtable\AirtableDeleteRecordNode;
use App\Nodes\Integrations\Airtable\AirtableFindRecordNode;
use App\Nodes\Integrations\Airtable\AirtableGetMeNode;
use App\Nodes\Integrations\Airtable\AirtableGetRecordNode;
use App\Nodes\Integrations\Airtable\AirtableListBasesNode;
use App\Nodes\Integrations\Airtable\AirtableListCommentsNode;
use App\Nodes\Integrations\Airtable\AirtableListNewRecordsNode;
use App\Nodes\Integrations\Airtable\AirtableListRecordsNode;
use App\Nodes\Integrations\Airtable\AirtableListTablesNode;
use App\Nodes\Integrations\Airtable\AirtableListUpdatedRecordsNode;
use App\Nodes\Integrations\Airtable\AirtableUpdateRecordNode;
use App\Nodes\Integrations\Airtable\AirtableUpsertRecordNode;
use App\Nodes\Integrations\Asana\AsanaAddCommentNode;
use App\Nodes\Integrations\Asana\AsanaAddTagNode;
use App\Nodes\Integrations\Asana\AsanaAddTaskToProjectNode;
use App\Nodes\Integrations\Asana\AsanaCompleteTaskNode;
use App\Nodes\Integrations\Asana\AsanaCreateProjectNode;
use App\Nodes\Integrations\Asana\AsanaCreateSectionNode;
use App\Nodes\Integrations\Asana\AsanaCreateSubtaskNode;
use App\Nodes\Integrations\Asana\AsanaCreateTagNode;
use App\Nodes\Integrations\Asana\AsanaCreateTaskNode;
use App\Nodes\Integrations\Asana\AsanaDeleteTaskNode;
use App\Nodes\Integrations\Asana\AsanaGetMeNode;
use App\Nodes\Integrations\Asana\AsanaGetProjectNode;
use App\Nodes\Integrations\Asana\AsanaGetTaskNode;
use App\Nodes\Integrations\Asana\AsanaListCommentsNode;
use App\Nodes\Integrations\Asana\AsanaListCompletedTasksNode;
use App\Nodes\Integrations\Asana\AsanaListNewTasksNode;
use App\Nodes\Integrations\Asana\AsanaListProjectsNode;
use App\Nodes\Integrations\Asana\AsanaListSectionsNode;
use App\Nodes\Integrations\Asana\AsanaListSubtasksNode;
use App\Nodes\Integrations\Asana\AsanaListTagsNode;
use App\Nodes\Integrations\Asana\AsanaListTasksNode;
use App\Nodes\Integrations\Asana\AsanaListUpdatedTasksNode;
use App\Nodes\Integrations\Asana\AsanaListUsersNode;
use App\Nodes\Integrations\Asana\AsanaListWorkspacesNode;
use App\Nodes\Integrations\Asana\AsanaMoveTaskToSectionNode;
use App\Nodes\Integrations\Asana\AsanaRemoveTagNode;
use App\Nodes\Integrations\Asana\AsanaRemoveTaskFromProjectNode;
use App\Nodes\Integrations\Asana\AsanaSearchTasksNode;
use App\Nodes\Integrations\Asana\AsanaUpdateProjectNode;
use App\Nodes\Integrations\Asana\AsanaUpdateTaskNode;
use App\Nodes\Integrations\Brevo\BrevoAddContactsToListNode;
use App\Nodes\Integrations\Brevo\BrevoCreateListNode;
use App\Nodes\Integrations\Brevo\BrevoCreateOrUpdateContactNode;
use App\Nodes\Integrations\Brevo\BrevoDeleteContactNode;
use App\Nodes\Integrations\Brevo\BrevoGetAccountNode;
use App\Nodes\Integrations\Brevo\BrevoGetCampaignNode;
use App\Nodes\Integrations\Brevo\BrevoGetContactNode;
use App\Nodes\Integrations\Brevo\BrevoListAttributesNode;
use App\Nodes\Integrations\Brevo\BrevoListCampaignsNode;
use App\Nodes\Integrations\Brevo\BrevoListContactsNode;
use App\Nodes\Integrations\Brevo\BrevoListListsNode;
use App\Nodes\Integrations\Brevo\BrevoListSendersNode;
use App\Nodes\Integrations\Brevo\BrevoListTemplatesNode;
use App\Nodes\Integrations\Brevo\BrevoListUpdatedContactsNode;
use App\Nodes\Integrations\Brevo\BrevoRemoveContactsFromListNode;
use App\Nodes\Integrations\Brevo\BrevoSendCampaignNode;
use App\Nodes\Integrations\Brevo\BrevoSendEmailNode;
use App\Nodes\Integrations\Brevo\BrevoSendSmsNode;
use App\Nodes\Integrations\Brevo\BrevoTrackEventNode;
use App\Nodes\Integrations\Brevo\BrevoUpdateContactNode;
use App\Nodes\Integrations\ClickUp\ClickUpAddChecklistItemNode;
use App\Nodes\Integrations\ClickUp\ClickUpAddCommentNode;
use App\Nodes\Integrations\ClickUp\ClickUpAddTagNode;
use App\Nodes\Integrations\ClickUp\ClickUpCreateChecklistNode;
use App\Nodes\Integrations\ClickUp\ClickUpCreateListNode;
use App\Nodes\Integrations\ClickUp\ClickUpCreateTaskNode;
use App\Nodes\Integrations\ClickUp\ClickUpCreateTimeEntryNode;
use App\Nodes\Integrations\ClickUp\ClickUpDeleteTaskNode;
use App\Nodes\Integrations\ClickUp\ClickUpFilterTasksNode;
use App\Nodes\Integrations\ClickUp\ClickUpGetListNode;
use App\Nodes\Integrations\ClickUp\ClickUpGetMeNode;
use App\Nodes\Integrations\ClickUp\ClickUpGetTaskNode;
use App\Nodes\Integrations\ClickUp\ClickUpListCommentsNode;
use App\Nodes\Integrations\ClickUp\ClickUpListCustomFieldsNode;
use App\Nodes\Integrations\ClickUp\ClickUpListFoldersNode;
use App\Nodes\Integrations\ClickUp\ClickUpListListsNode;
use App\Nodes\Integrations\ClickUp\ClickUpListNewTasksNode;
use App\Nodes\Integrations\ClickUp\ClickUpListSpacesNode;
use App\Nodes\Integrations\ClickUp\ClickUpListTasksNode;
use App\Nodes\Integrations\ClickUp\ClickUpListTimeEntriesNode;
use App\Nodes\Integrations\ClickUp\ClickUpListUpdatedTasksNode;
use App\Nodes\Integrations\ClickUp\ClickUpListWorkspacesNode;
use App\Nodes\Integrations\ClickUp\ClickUpRemoveTagNode;
use App\Nodes\Integrations\ClickUp\ClickUpSetCustomFieldNode;
use App\Nodes\Integrations\ClickUp\ClickUpUpdateTaskNode;
use App\Nodes\Integrations\Discord\DiscordAddReactionNode;
use App\Nodes\Integrations\Discord\DiscordAddRoleNode;
use App\Nodes\Integrations\Discord\DiscordAddThreadMemberNode;
use App\Nodes\Integrations\Discord\DiscordArchiveThreadNode;
use App\Nodes\Integrations\Discord\DiscordBanMemberNode;
use App\Nodes\Integrations\Discord\DiscordBulkDeleteMessagesNode;
use App\Nodes\Integrations\Discord\DiscordClearReactionsNode;
use App\Nodes\Integrations\Discord\DiscordCreateChannelNode;
use App\Nodes\Integrations\Discord\DiscordCreateInviteNode;
use App\Nodes\Integrations\Discord\DiscordCreateRoleNode;
use App\Nodes\Integrations\Discord\DiscordCreateThreadNode;
use App\Nodes\Integrations\Discord\DiscordDeleteChannelNode;
use App\Nodes\Integrations\Discord\DiscordDeleteCommandNode;
use App\Nodes\Integrations\Discord\DiscordDeleteInviteNode;
use App\Nodes\Integrations\Discord\DiscordDeleteMessageNode;
use App\Nodes\Integrations\Discord\DiscordDeleteRoleNode;
use App\Nodes\Integrations\Discord\DiscordEditChannelNode;
use App\Nodes\Integrations\Discord\DiscordEditMessageNode;
use App\Nodes\Integrations\Discord\DiscordGetChannelNode;
use App\Nodes\Integrations\Discord\DiscordGetGuildNode;
use App\Nodes\Integrations\Discord\DiscordGetMemberNode;
use App\Nodes\Integrations\Discord\DiscordGetMeNode;
use App\Nodes\Integrations\Discord\DiscordGetMessageNode;
use App\Nodes\Integrations\Discord\DiscordGetUserNode;
use App\Nodes\Integrations\Discord\DiscordKickMemberNode;
use App\Nodes\Integrations\Discord\DiscordListActiveThreadsNode;
use App\Nodes\Integrations\Discord\DiscordListBansNode;
use App\Nodes\Integrations\Discord\DiscordListChannelsNode;
use App\Nodes\Integrations\Discord\DiscordListCommandsNode;
use App\Nodes\Integrations\Discord\DiscordListGuildsNode;
use App\Nodes\Integrations\Discord\DiscordListInvitesNode;
use App\Nodes\Integrations\Discord\DiscordListMembersNode;
use App\Nodes\Integrations\Discord\DiscordListMessagesNode;
use App\Nodes\Integrations\Discord\DiscordListNewChannelsNode;
use App\Nodes\Integrations\Discord\DiscordListNewMembersNode;
use App\Nodes\Integrations\Discord\DiscordListNewMessagesNode;
use App\Nodes\Integrations\Discord\DiscordListNewThreadsNode;
use App\Nodes\Integrations\Discord\DiscordListPinnedMessagesNode;
use App\Nodes\Integrations\Discord\DiscordListReactionsNode;
use App\Nodes\Integrations\Discord\DiscordListRolesNode;
use App\Nodes\Integrations\Discord\DiscordNewButtonClickNode;
use App\Nodes\Integrations\Discord\DiscordNewSlashCommandNode;
use App\Nodes\Integrations\Discord\DiscordPinMessageNode;
use App\Nodes\Integrations\Discord\DiscordRegisterCommandNode;
use App\Nodes\Integrations\Discord\DiscordRemoveReactionNode;
use App\Nodes\Integrations\Discord\DiscordRemoveRoleNode;
use App\Nodes\Integrations\Discord\DiscordRemoveThreadMemberNode;
use App\Nodes\Integrations\Discord\DiscordRespondToInteractionNode;
use App\Nodes\Integrations\Discord\DiscordSearchMembersNode;
use App\Nodes\Integrations\Discord\DiscordSendDirectMessageNode;
use App\Nodes\Integrations\Discord\DiscordSendMessageNode;
use App\Nodes\Integrations\Discord\DiscordSendWebhookMessageNode;
use App\Nodes\Integrations\Discord\DiscordSetNicknameNode;
use App\Nodes\Integrations\Discord\DiscordTimeoutMemberNode;
use App\Nodes\Integrations\Discord\DiscordUnbanMemberNode;
use App\Nodes\Integrations\Discord\DiscordUnpinMessageNode;
use App\Nodes\Integrations\Email\EmailParseInboundNode;
use App\Nodes\Integrations\Email\EmailSendEmailNode;
use App\Nodes\Integrations\Email\EmailSendToManyNode;
use App\Nodes\Integrations\Email\EmailValidateAddressNode;
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
use App\Nodes\Integrations\Gmail\GmailArchiveMessageNode;
use App\Nodes\Integrations\Gmail\GmailBatchModifyMessagesNode;
use App\Nodes\Integrations\Gmail\GmailCreateDraftNode;
use App\Nodes\Integrations\Gmail\GmailCreateLabelNode;
use App\Nodes\Integrations\Gmail\GmailDeleteDraftNode;
use App\Nodes\Integrations\Gmail\GmailDeleteLabelNode;
use App\Nodes\Integrations\Gmail\GmailDeleteMessageNode;
use App\Nodes\Integrations\Gmail\GmailForwardMessageNode;
use App\Nodes\Integrations\Gmail\GmailGetAttachmentNode;
use App\Nodes\Integrations\Gmail\GmailGetDraftNode;
use App\Nodes\Integrations\Gmail\GmailGetMessageNode;
use App\Nodes\Integrations\Gmail\GmailGetProfileNode;
use App\Nodes\Integrations\Gmail\GmailGetThreadNode;
use App\Nodes\Integrations\Gmail\GmailListDraftsNode;
use App\Nodes\Integrations\Gmail\GmailListLabelsNode;
use App\Nodes\Integrations\Gmail\GmailListMessagesNode;
use App\Nodes\Integrations\Gmail\GmailListMessagesWithAttachmentsNode;
use App\Nodes\Integrations\Gmail\GmailMarkReadNode;
use App\Nodes\Integrations\Gmail\GmailModifyMessageNode;
use App\Nodes\Integrations\Gmail\GmailReplyToMessageNode;
use App\Nodes\Integrations\Gmail\GmailSendDraftNode;
use App\Nodes\Integrations\Gmail\GmailSendEmailNode;
use App\Nodes\Integrations\Gmail\GmailTrashMessageNode;
use App\Nodes\Integrations\Gmail\GmailTrashThreadNode;
use App\Nodes\Integrations\Gmail\GmailUntrashMessageNode;
use App\Nodes\Integrations\Gmail\GmailUpdateDraftNode;
use App\Nodes\Integrations\Gmail\GmailUpdateLabelNode;
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
use App\Nodes\Integrations\GoogleForms\GoogleFormsAddQuestionNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsCreateFormNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsDeleteItemNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsGetFormNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsGetResponseNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsListResponsesNode;
use App\Nodes\Integrations\GoogleForms\GoogleFormsUpdateFormInfoNode;
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
use App\Nodes\Integrations\Jira\JiraAddAttachmentNode;
use App\Nodes\Integrations\Jira\JiraAddCommentNode;
use App\Nodes\Integrations\Jira\JiraAddUserToGroupNode;
use App\Nodes\Integrations\Jira\JiraAddUserToIssueNode;
use App\Nodes\Integrations\Jira\JiraCreateCustomerRequestNode;
use App\Nodes\Integrations\Jira\JiraCreateGroupNode;
use App\Nodes\Integrations\Jira\JiraCreateIssueLinkNode;
use App\Nodes\Integrations\Jira\JiraCreateIssueNode;
use App\Nodes\Integrations\Jira\JiraCreateProjectNode;
use App\Nodes\Integrations\Jira\JiraDeleteCommentNode;
use App\Nodes\Integrations\Jira\JiraDeleteIssueLinkNode;
use App\Nodes\Integrations\Jira\JiraDeleteIssueNode;
use App\Nodes\Integrations\Jira\JiraDeleteProjectNode;
use App\Nodes\Integrations\Jira\JiraDownloadAttachmentNode;
use App\Nodes\Integrations\Jira\JiraGetCustomerRequestNode;
use App\Nodes\Integrations\Jira\JiraGetEditMetadataNode;
use App\Nodes\Integrations\Jira\JiraGetIssueLinksNode;
use App\Nodes\Integrations\Jira\JiraGetIssueNode;
use App\Nodes\Integrations\Jira\JiraGetMyIssuesNode;
use App\Nodes\Integrations\Jira\JiraGetMyPermissionsNode;
use App\Nodes\Integrations\Jira\JiraGetMyRecentActivityNode;
use App\Nodes\Integrations\Jira\JiraGetMyselfNode;
use App\Nodes\Integrations\Jira\JiraGetProjectNode;
use App\Nodes\Integrations\Jira\JiraGetRequestTypeFieldsNode;
use App\Nodes\Integrations\Jira\JiraGetRequestTypesNode;
use App\Nodes\Integrations\Jira\JiraListCommentsNode;
use App\Nodes\Integrations\Jira\JiraListCustomerRequestsNode;
use App\Nodes\Integrations\Jira\JiraListFieldsNode;
use App\Nodes\Integrations\Jira\JiraListFiltersNode;
use App\Nodes\Integrations\Jira\JiraListGroupsNode;
use App\Nodes\Integrations\Jira\JiraListIssueLinkTypesNode;
use App\Nodes\Integrations\Jira\JiraListIssuesNode;
use App\Nodes\Integrations\Jira\JiraListIssueTypesNode;
use App\Nodes\Integrations\Jira\JiraListNewIssuesNode;
use App\Nodes\Integrations\Jira\JiraListPrioritiesNode;
use App\Nodes\Integrations\Jira\JiraListProjectsNode;
use App\Nodes\Integrations\Jira\JiraListServiceDesksNode;
use App\Nodes\Integrations\Jira\JiraListTransitionsNode;
use App\Nodes\Integrations\Jira\JiraListUpdatedIssuesNode;
use App\Nodes\Integrations\Jira\JiraListUsersNode;
use App\Nodes\Integrations\Jira\JiraRemoveUserFromGroupNode;
use App\Nodes\Integrations\Jira\JiraSearchIssuesNode;
use App\Nodes\Integrations\Jira\JiraTransitionIssueNode;
use App\Nodes\Integrations\Jira\JiraUpdateCommentNode;
use App\Nodes\Integrations\Jira\JiraUpdateIssueNode;
use App\Nodes\Integrations\Jira\JiraUpdateProjectNode;
use App\Nodes\Integrations\Kit\KitAddSubscriberToFormNode;
use App\Nodes\Integrations\Kit\KitAddSubscriberToSequenceNode;
use App\Nodes\Integrations\Kit\KitCreateBroadcastNode;
use App\Nodes\Integrations\Kit\KitCreateCustomFieldNode;
use App\Nodes\Integrations\Kit\KitCreateOrUpdateSubscriberNode;
use App\Nodes\Integrations\Kit\KitCreatePurchaseNode;
use App\Nodes\Integrations\Kit\KitCreateTagNode;
use App\Nodes\Integrations\Kit\KitDeleteBroadcastNode;
use App\Nodes\Integrations\Kit\KitGetAccountNode;
use App\Nodes\Integrations\Kit\KitGetBroadcastNode;
use App\Nodes\Integrations\Kit\KitGetBroadcastStatsNode;
use App\Nodes\Integrations\Kit\KitGetSubscriberNode;
use App\Nodes\Integrations\Kit\KitListBouncedSubscribersNode;
use App\Nodes\Integrations\Kit\KitListBroadcastsNode;
use App\Nodes\Integrations\Kit\KitListCustomFieldsNode;
use App\Nodes\Integrations\Kit\KitListEmailTemplatesNode;
use App\Nodes\Integrations\Kit\KitListFormsNode;
use App\Nodes\Integrations\Kit\KitListFormSubscribersNode;
use App\Nodes\Integrations\Kit\KitListPurchasesNode;
use App\Nodes\Integrations\Kit\KitListSequencesNode;
use App\Nodes\Integrations\Kit\KitListSubscribersNode;
use App\Nodes\Integrations\Kit\KitListSubscriberTagsNode;
use App\Nodes\Integrations\Kit\KitListTaggedSubscribersNode;
use App\Nodes\Integrations\Kit\KitListTagsNode;
use App\Nodes\Integrations\Kit\KitListUnsubscribesNode;
use App\Nodes\Integrations\Kit\KitTagSubscriberNode;
use App\Nodes\Integrations\Kit\KitUnsubscribeSubscriberNode;
use App\Nodes\Integrations\Kit\KitUntagSubscriberNode;
use App\Nodes\Integrations\Kit\KitUpdateBroadcastNode;
use App\Nodes\Integrations\Kit\KitUpdateSubscriberNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoAddProfileToListNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoCreateListNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoDeleteListNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoGetAccountNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoGetProfileNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListCampaignsNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListEventsNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListFlowsNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListListProfilesNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListListsNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListMetricsNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListProfilesNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListSegmentProfilesNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListSegmentsNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoListTemplatesNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoRemoveProfileFromListNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoSetFlowStatusNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoSubscribeProfileNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoTrackEventNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoUnsubscribeProfileNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoUpdateProfileNode;
use App\Nodes\Integrations\Klaviyo\KlaviyoUpsertProfileNode;
use App\Nodes\Integrations\Linear\LinearCreateCommentNode;
use App\Nodes\Integrations\Linear\LinearCreateInitiativeNode;
use App\Nodes\Integrations\Linear\LinearCreateIssueNode;
use App\Nodes\Integrations\Linear\LinearCreateIssueRelationNode;
use App\Nodes\Integrations\Linear\LinearCreateProjectNode;
use App\Nodes\Integrations\Linear\LinearCreateStatusUpdateNode;
use App\Nodes\Integrations\Linear\LinearDeleteCommentNode;
use App\Nodes\Integrations\Linear\LinearDeleteInitiativeNode;
use App\Nodes\Integrations\Linear\LinearDeleteIssueNode;
use App\Nodes\Integrations\Linear\LinearDeleteIssueRelationNode;
use App\Nodes\Integrations\Linear\LinearDeleteProjectNode;
use App\Nodes\Integrations\Linear\LinearDeleteStatusUpdateNode;
use App\Nodes\Integrations\Linear\LinearGetInitiativeNode;
use App\Nodes\Integrations\Linear\LinearGetIssueNode;
use App\Nodes\Integrations\Linear\LinearGetMeNode;
use App\Nodes\Integrations\Linear\LinearGetProjectNode;
use App\Nodes\Integrations\Linear\LinearListCommentsNode;
use App\Nodes\Integrations\Linear\LinearListCyclesNode;
use App\Nodes\Integrations\Linear\LinearListInitiativesNode;
use App\Nodes\Integrations\Linear\LinearListIssueRelationsNode;
use App\Nodes\Integrations\Linear\LinearListIssuesNode;
use App\Nodes\Integrations\Linear\LinearListLabelsNode;
use App\Nodes\Integrations\Linear\LinearListNewCommentsNode;
use App\Nodes\Integrations\Linear\LinearListNewIssuesNode;
use App\Nodes\Integrations\Linear\LinearListProjectsNode;
use App\Nodes\Integrations\Linear\LinearListStatusUpdatesNode;
use App\Nodes\Integrations\Linear\LinearListTeamsNode;
use App\Nodes\Integrations\Linear\LinearListUpdatedIssuesNode;
use App\Nodes\Integrations\Linear\LinearListUsersNode;
use App\Nodes\Integrations\Linear\LinearListWorkflowStatesNode;
use App\Nodes\Integrations\Linear\LinearSearchIssuesNode;
use App\Nodes\Integrations\Linear\LinearUpdateCommentNode;
use App\Nodes\Integrations\Linear\LinearUpdateInitiativeNode;
use App\Nodes\Integrations\Linear\LinearUpdateIssueNode;
use App\Nodes\Integrations\Linear\LinearUpdateIssueRelationNode;
use App\Nodes\Integrations\Linear\LinearUpdateProjectNode;
use App\Nodes\Integrations\Linear\LinearUpdateStatusUpdateNode;
use App\Nodes\Integrations\Mailchimp\MailchimpAddNoteNode;
use App\Nodes\Integrations\Mailchimp\MailchimpAddOrUpdateMemberNode;
use App\Nodes\Integrations\Mailchimp\MailchimpAddTagsNode;
use App\Nodes\Integrations\Mailchimp\MailchimpArchiveMemberNode;
use App\Nodes\Integrations\Mailchimp\MailchimpCheckSendReadinessNode;
use App\Nodes\Integrations\Mailchimp\MailchimpCreateAudienceNode;
use App\Nodes\Integrations\Mailchimp\MailchimpCreateCampaignNode;
use App\Nodes\Integrations\Mailchimp\MailchimpCreateSegmentNode;
use App\Nodes\Integrations\Mailchimp\MailchimpDeleteAudienceNode;
use App\Nodes\Integrations\Mailchimp\MailchimpDeleteCampaignNode;
use App\Nodes\Integrations\Mailchimp\MailchimpDeleteMemberNode;
use App\Nodes\Integrations\Mailchimp\MailchimpGetAccountNode;
use App\Nodes\Integrations\Mailchimp\MailchimpGetAudienceNode;
use App\Nodes\Integrations\Mailchimp\MailchimpGetCampaignNode;
use App\Nodes\Integrations\Mailchimp\MailchimpGetCampaignReportNode;
use App\Nodes\Integrations\Mailchimp\MailchimpGetMemberActivityNode;
use App\Nodes\Integrations\Mailchimp\MailchimpGetMemberNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListAudiencesNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListCampaignClicksNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListCampaignsNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListCleanedMembersNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListMembersNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListMemberTagsNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListMergeFieldsNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListNewSubscribersNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListNotesNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListSegmentsNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListTemplatesNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListUnsubscribesNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListUpdatedMembersNode;
use App\Nodes\Integrations\Mailchimp\MailchimpRemoveTagsNode;
use App\Nodes\Integrations\Mailchimp\MailchimpReplicateCampaignNode;
use App\Nodes\Integrations\Mailchimp\MailchimpScheduleCampaignNode;
use App\Nodes\Integrations\Mailchimp\MailchimpSearchMembersNode;
use App\Nodes\Integrations\Mailchimp\MailchimpSendCampaignNode;
use App\Nodes\Integrations\Mailchimp\MailchimpSendTestEmailNode;
use App\Nodes\Integrations\Mailchimp\MailchimpSetCampaignContentNode;
use App\Nodes\Integrations\Mailchimp\MailchimpUnscheduleCampaignNode;
use App\Nodes\Integrations\Mailchimp\MailchimpUnsubscribeMemberNode;
use App\Nodes\Integrations\Mailchimp\MailchimpUpdateMemberNode;
use App\Nodes\Integrations\Mailgun\MailgunAddMemberNode;
use App\Nodes\Integrations\Mailgun\MailgunAddSuppressionNode;
use App\Nodes\Integrations\Mailgun\MailgunCreateMailingListNode;
use App\Nodes\Integrations\Mailgun\MailgunDeleteMailingListNode;
use App\Nodes\Integrations\Mailgun\MailgunGetDomainNode;
use App\Nodes\Integrations\Mailgun\MailgunGetMemberNode;
use App\Nodes\Integrations\Mailgun\MailgunGetStatsNode;
use App\Nodes\Integrations\Mailgun\MailgunGetTemplateNode;
use App\Nodes\Integrations\Mailgun\MailgunListDomainsNode;
use App\Nodes\Integrations\Mailgun\MailgunListEventsNode;
use App\Nodes\Integrations\Mailgun\MailgunListMailingListsNode;
use App\Nodes\Integrations\Mailgun\MailgunListMembersNode;
use App\Nodes\Integrations\Mailgun\MailgunListSuppressionsNode;
use App\Nodes\Integrations\Mailgun\MailgunListTemplatesNode;
use App\Nodes\Integrations\Mailgun\MailgunRemoveMemberNode;
use App\Nodes\Integrations\Mailgun\MailgunRemoveSuppressionNode;
use App\Nodes\Integrations\Mailgun\MailgunSendEmailNode;
use App\Nodes\Integrations\Mailgun\MailgunUpdateMemberNode;
use App\Nodes\Integrations\Mailgun\MailgunValidateEmailNode;
use App\Nodes\Integrations\Mailgun\MailgunVerifyDomainNode;
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
use App\Nodes\Integrations\Notion\NotionListDeletedDatabaseItemsNode;
use App\Nodes\Integrations\Notion\NotionListNewCommentsNode;
use App\Nodes\Integrations\Notion\NotionListNewDatabaseItemsNode;
use App\Nodes\Integrations\Notion\NotionListNewDatabasesNode;
use App\Nodes\Integrations\Notion\NotionListNewPagesNode;
use App\Nodes\Integrations\Notion\NotionListNewUsersNode;
use App\Nodes\Integrations\Notion\NotionListUpdatedDatabaseItemsNode;
use App\Nodes\Integrations\Notion\NotionListUpdatedPagesNode;
use App\Nodes\Integrations\Notion\NotionListUsersNode;
use App\Nodes\Integrations\Notion\NotionQueryDatabaseNode;
use App\Nodes\Integrations\Notion\NotionRestorePageNode;
use App\Nodes\Integrations\Notion\NotionSearchNode;
use App\Nodes\Integrations\Notion\NotionUpdateBlockNode;
use App\Nodes\Integrations\Notion\NotionUpdateDatabaseNode;
use App\Nodes\Integrations\Notion\NotionUpdatePageNode;
use App\Nodes\Integrations\Outlook\OutlookCopyMessageNode;
use App\Nodes\Integrations\Outlook\OutlookCreateDraftNode;
use App\Nodes\Integrations\Outlook\OutlookCreateEventNode;
use App\Nodes\Integrations\Outlook\OutlookCreateFolderNode;
use App\Nodes\Integrations\Outlook\OutlookDeleteEventNode;
use App\Nodes\Integrations\Outlook\OutlookDeleteMessageNode;
use App\Nodes\Integrations\Outlook\OutlookFlagMessageNode;
use App\Nodes\Integrations\Outlook\OutlookForwardMessageNode;
use App\Nodes\Integrations\Outlook\OutlookGetAttachmentNode;
use App\Nodes\Integrations\Outlook\OutlookGetAutoReplyNode;
use App\Nodes\Integrations\Outlook\OutlookGetMessageNode;
use App\Nodes\Integrations\Outlook\OutlookGetProfileNode;
use App\Nodes\Integrations\Outlook\OutlookListAttachmentsNode;
use App\Nodes\Integrations\Outlook\OutlookListCancelledEventsNode;
use App\Nodes\Integrations\Outlook\OutlookListEventsNode;
use App\Nodes\Integrations\Outlook\OutlookListFoldersNode;
use App\Nodes\Integrations\Outlook\OutlookListMessagesNode;
use App\Nodes\Integrations\Outlook\OutlookListNewEventsNode;
use App\Nodes\Integrations\Outlook\OutlookListUpcomingEventsNode;
use App\Nodes\Integrations\Outlook\OutlookListUpdatedEventsNode;
use App\Nodes\Integrations\Outlook\OutlookMarkReadNode;
use App\Nodes\Integrations\Outlook\OutlookMoveMessageNode;
use App\Nodes\Integrations\Outlook\OutlookReplyAllNode;
use App\Nodes\Integrations\Outlook\OutlookReplyToMessageNode;
use App\Nodes\Integrations\Outlook\OutlookSendDraftNode;
use App\Nodes\Integrations\Outlook\OutlookSendEmailNode;
use App\Nodes\Integrations\Outlook\OutlookSetAutoReplyNode;
use App\Nodes\Integrations\Outlook\OutlookSetCategoriesNode;
use App\Nodes\Integrations\Postmark\PostmarkActivateBounceNode;
use App\Nodes\Integrations\Postmark\PostmarkAddSuppressionsNode;
use App\Nodes\Integrations\Postmark\PostmarkDeleteSuppressionsNode;
use App\Nodes\Integrations\Postmark\PostmarkGetBounceNode;
use App\Nodes\Integrations\Postmark\PostmarkGetDeliveryStatsNode;
use App\Nodes\Integrations\Postmark\PostmarkGetInboundMessageNode;
use App\Nodes\Integrations\Postmark\PostmarkGetOutboundMessageNode;
use App\Nodes\Integrations\Postmark\PostmarkGetServerNode;
use App\Nodes\Integrations\Postmark\PostmarkGetStatsNode;
use App\Nodes\Integrations\Postmark\PostmarkGetTemplateNode;
use App\Nodes\Integrations\Postmark\PostmarkListBouncesNode;
use App\Nodes\Integrations\Postmark\PostmarkListClicksNode;
use App\Nodes\Integrations\Postmark\PostmarkListInboundMessagesNode;
use App\Nodes\Integrations\Postmark\PostmarkListMessageStreamsNode;
use App\Nodes\Integrations\Postmark\PostmarkListOpensNode;
use App\Nodes\Integrations\Postmark\PostmarkListOutboundMessagesNode;
use App\Nodes\Integrations\Postmark\PostmarkListSendersNode;
use App\Nodes\Integrations\Postmark\PostmarkListSpamComplaintsNode;
use App\Nodes\Integrations\Postmark\PostmarkListSuppressionsNode;
use App\Nodes\Integrations\Postmark\PostmarkListTemplatesNode;
use App\Nodes\Integrations\Postmark\PostmarkSendBatchNode;
use App\Nodes\Integrations\Postmark\PostmarkSendEmailNode;
use App\Nodes\Integrations\Postmark\PostmarkSendTemplateEmailNode;
use App\Nodes\Integrations\Resend\ResendAddContactNode;
use App\Nodes\Integrations\Resend\ResendCancelEmailNode;
use App\Nodes\Integrations\Resend\ResendCreateAudienceNode;
use App\Nodes\Integrations\Resend\ResendCreateBroadcastNode;
use App\Nodes\Integrations\Resend\ResendDeleteAudienceNode;
use App\Nodes\Integrations\Resend\ResendDeleteBroadcastNode;
use App\Nodes\Integrations\Resend\ResendDeleteContactNode;
use App\Nodes\Integrations\Resend\ResendGetContactNode;
use App\Nodes\Integrations\Resend\ResendGetEmailNode;
use App\Nodes\Integrations\Resend\ResendListAudiencesNode;
use App\Nodes\Integrations\Resend\ResendListBroadcastsNode;
use App\Nodes\Integrations\Resend\ResendListContactsNode;
use App\Nodes\Integrations\Resend\ResendListDomainsNode;
use App\Nodes\Integrations\Resend\ResendListEmailsNode;
use App\Nodes\Integrations\Resend\ResendRescheduleEmailNode;
use App\Nodes\Integrations\Resend\ResendSendBatchNode;
use App\Nodes\Integrations\Resend\ResendSendBroadcastNode;
use App\Nodes\Integrations\Resend\ResendSendEmailNode;
use App\Nodes\Integrations\Resend\ResendVerifyDomainNode;
use App\Nodes\Integrations\SendGrid\SendGridAddGlobalUnsubscribeNode;
use App\Nodes\Integrations\SendGrid\SendGridCreateListNode;
use App\Nodes\Integrations\SendGrid\SendGridDeleteContactNode;
use App\Nodes\Integrations\SendGrid\SendGridDeleteListNode;
use App\Nodes\Integrations\SendGrid\SendGridGetAccountNode;
use App\Nodes\Integrations\SendGrid\SendGridGetContactNode;
use App\Nodes\Integrations\SendGrid\SendGridGetStatsNode;
use App\Nodes\Integrations\SendGrid\SendGridListBlocksNode;
use App\Nodes\Integrations\SendGrid\SendGridListBouncesNode;
use App\Nodes\Integrations\SendGrid\SendGridListInvalidEmailsNode;
use App\Nodes\Integrations\SendGrid\SendGridListListsNode;
use App\Nodes\Integrations\SendGrid\SendGridListSendersNode;
use App\Nodes\Integrations\SendGrid\SendGridListSpamReportsNode;
use App\Nodes\Integrations\SendGrid\SendGridListTemplatesNode;
use App\Nodes\Integrations\SendGrid\SendGridListUnsubscribesNode;
use App\Nodes\Integrations\SendGrid\SendGridRemoveContactFromListNode;
use App\Nodes\Integrations\SendGrid\SendGridRemoveSuppressionNode;
use App\Nodes\Integrations\SendGrid\SendGridSearchContactsNode;
use App\Nodes\Integrations\SendGrid\SendGridSendEmailNode;
use App\Nodes\Integrations\SendGrid\SendGridUpsertContactNode;
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
use App\Nodes\Integrations\Telegram\TelegramAnswerCallbackQueryNode;
use App\Nodes\Integrations\Telegram\TelegramApproveChatJoinRequestNode;
use App\Nodes\Integrations\Telegram\TelegramBanChatMemberNode;
use App\Nodes\Integrations\Telegram\TelegramBotChatStatusNode;
use App\Nodes\Integrations\Telegram\TelegramCopyMessageNode;
use App\Nodes\Integrations\Telegram\TelegramCreateChatInviteLinkNode;
use App\Nodes\Integrations\Telegram\TelegramDeclineChatJoinRequestNode;
use App\Nodes\Integrations\Telegram\TelegramDeleteMessageNode;
use App\Nodes\Integrations\Telegram\TelegramDeleteMessagesNode;
use App\Nodes\Integrations\Telegram\TelegramDeleteMyCommandsNode;
use App\Nodes\Integrations\Telegram\TelegramEditedMessageNode;
use App\Nodes\Integrations\Telegram\TelegramEditMessageButtonsNode;
use App\Nodes\Integrations\Telegram\TelegramEditMessageCaptionNode;
use App\Nodes\Integrations\Telegram\TelegramEditMessageTextNode;
use App\Nodes\Integrations\Telegram\TelegramForwardMessageNode;
use App\Nodes\Integrations\Telegram\TelegramGetChatAdministratorsNode;
use App\Nodes\Integrations\Telegram\TelegramGetChatMemberCountNode;
use App\Nodes\Integrations\Telegram\TelegramGetChatMemberNode;
use App\Nodes\Integrations\Telegram\TelegramGetChatNode;
use App\Nodes\Integrations\Telegram\TelegramGetFileNode;
use App\Nodes\Integrations\Telegram\TelegramGetMeNode;
use App\Nodes\Integrations\Telegram\TelegramGetMyCommandsNode;
use App\Nodes\Integrations\Telegram\TelegramGetWebhookInfoNode;
use App\Nodes\Integrations\Telegram\TelegramLeaveChatNode;
use App\Nodes\Integrations\Telegram\TelegramMemberJoinedNode;
use App\Nodes\Integrations\Telegram\TelegramMemberLeftNode;
use App\Nodes\Integrations\Telegram\TelegramNewCallbackQueryNode;
use App\Nodes\Integrations\Telegram\TelegramNewChannelPostNode;
use App\Nodes\Integrations\Telegram\TelegramNewJoinRequestNode;
use App\Nodes\Integrations\Telegram\TelegramNewMessageNode;
use App\Nodes\Integrations\Telegram\TelegramNewPollAnswerNode;
use App\Nodes\Integrations\Telegram\TelegramPinChatMessageNode;
use App\Nodes\Integrations\Telegram\TelegramRestrictChatMemberNode;
use App\Nodes\Integrations\Telegram\TelegramSendAnimationNode;
use App\Nodes\Integrations\Telegram\TelegramSendAudioNode;
use App\Nodes\Integrations\Telegram\TelegramSendChatActionNode;
use App\Nodes\Integrations\Telegram\TelegramSendContactNode;
use App\Nodes\Integrations\Telegram\TelegramSendDiceNode;
use App\Nodes\Integrations\Telegram\TelegramSendDocumentNode;
use App\Nodes\Integrations\Telegram\TelegramSendLocationNode;
use App\Nodes\Integrations\Telegram\TelegramSendMediaGroupNode;
use App\Nodes\Integrations\Telegram\TelegramSendMessageNode;
use App\Nodes\Integrations\Telegram\TelegramSendPhotoNode;
use App\Nodes\Integrations\Telegram\TelegramSendPollNode;
use App\Nodes\Integrations\Telegram\TelegramSendStickerNode;
use App\Nodes\Integrations\Telegram\TelegramSendVenueNode;
use App\Nodes\Integrations\Telegram\TelegramSendVideoNode;
use App\Nodes\Integrations\Telegram\TelegramSendVoiceNode;
use App\Nodes\Integrations\Telegram\TelegramSetChatDescriptionNode;
use App\Nodes\Integrations\Telegram\TelegramSetChatTitleNode;
use App\Nodes\Integrations\Telegram\TelegramSetMyCommandsNode;
use App\Nodes\Integrations\Telegram\TelegramStopPollNode;
use App\Nodes\Integrations\Telegram\TelegramUnbanChatMemberNode;
use App\Nodes\Integrations\Telegram\TelegramUnpinAllChatMessagesNode;
use App\Nodes\Integrations\Telegram\TelegramUnpinChatMessageNode;
use App\Nodes\Integrations\Trello\TrelloAddAttachmentNode;
use App\Nodes\Integrations\Trello\TrelloAddChecklistItemNode;
use App\Nodes\Integrations\Trello\TrelloAddCommentNode;
use App\Nodes\Integrations\Trello\TrelloAddLabelNode;
use App\Nodes\Integrations\Trello\TrelloAddMemberNode;
use App\Nodes\Integrations\Trello\TrelloArchiveAllCardsNode;
use App\Nodes\Integrations\Trello\TrelloCreateBoardNode;
use App\Nodes\Integrations\Trello\TrelloCreateCardNode;
use App\Nodes\Integrations\Trello\TrelloCreateChecklistNode;
use App\Nodes\Integrations\Trello\TrelloCreateLabelNode;
use App\Nodes\Integrations\Trello\TrelloCreateListNode;
use App\Nodes\Integrations\Trello\TrelloDeleteCardNode;
use App\Nodes\Integrations\Trello\TrelloGetBoardNode;
use App\Nodes\Integrations\Trello\TrelloGetCardNode;
use App\Nodes\Integrations\Trello\TrelloGetMemberNode;
use App\Nodes\Integrations\Trello\TrelloListActivityNode;
use App\Nodes\Integrations\Trello\TrelloListBoardsNode;
use App\Nodes\Integrations\Trello\TrelloListCardsNode;
use App\Nodes\Integrations\Trello\TrelloListCommentsNode;
use App\Nodes\Integrations\Trello\TrelloListLabelsNode;
use App\Nodes\Integrations\Trello\TrelloListListsNode;
use App\Nodes\Integrations\Trello\TrelloListMembersNode;
use App\Nodes\Integrations\Trello\TrelloMoveCardNode;
use App\Nodes\Integrations\Trello\TrelloRemoveLabelNode;
use App\Nodes\Integrations\Trello\TrelloRemoveMemberNode;
use App\Nodes\Integrations\Trello\TrelloSearchCardsNode;
use App\Nodes\Integrations\Trello\TrelloSetChecklistItemNode;
use App\Nodes\Integrations\Trello\TrelloUpdateCardNode;
use App\Nodes\Integrations\Trello\TrelloUpdateListNode;
use App\Nodes\Integrations\Twilio\TwilioCheckVerificationNode;
use App\Nodes\Integrations\Twilio\TwilioDeleteMessageNode;
use App\Nodes\Integrations\Twilio\TwilioDeleteRecordingNode;
use App\Nodes\Integrations\Twilio\TwilioGetAccountNode;
use App\Nodes\Integrations\Twilio\TwilioGetBalanceNode;
use App\Nodes\Integrations\Twilio\TwilioGetCallNode;
use App\Nodes\Integrations\Twilio\TwilioGetMessageNode;
use App\Nodes\Integrations\Twilio\TwilioListCallsNode;
use App\Nodes\Integrations\Twilio\TwilioListFailedMessagesNode;
use App\Nodes\Integrations\Twilio\TwilioListMessagesNode;
use App\Nodes\Integrations\Twilio\TwilioListNewCallsNode;
use App\Nodes\Integrations\Twilio\TwilioListNewIncomingMessagesNode;
use App\Nodes\Integrations\Twilio\TwilioListNewRecordingsNode;
use App\Nodes\Integrations\Twilio\TwilioListPhoneNumbersNode;
use App\Nodes\Integrations\Twilio\TwilioListRecordingsNode;
use App\Nodes\Integrations\Twilio\TwilioListVerifyServicesNode;
use App\Nodes\Integrations\Twilio\TwilioLookupPhoneNumberNode;
use App\Nodes\Integrations\Twilio\TwilioMakeCallNode;
use App\Nodes\Integrations\Twilio\TwilioRedactMessageNode;
use App\Nodes\Integrations\Twilio\TwilioSendSmsNode;
use App\Nodes\Integrations\Twilio\TwilioSendWhatsAppNode;
use App\Nodes\Integrations\Twilio\TwilioStartVerificationNode;
use App\Nodes\Integrations\Twilio\TwilioUpdateCallNode;
use App\Nodes\Integrations\Typeform\TypeformCreateFormNode;
use App\Nodes\Integrations\Typeform\TypeformCreateWebhookNode;
use App\Nodes\Integrations\Typeform\TypeformCreateWorkspaceNode;
use App\Nodes\Integrations\Typeform\TypeformDeleteFormNode;
use App\Nodes\Integrations\Typeform\TypeformDeleteResponsesNode;
use App\Nodes\Integrations\Typeform\TypeformDeleteWebhookNode;
use App\Nodes\Integrations\Typeform\TypeformDeleteWorkspaceNode;
use App\Nodes\Integrations\Typeform\TypeformGetFormNode;
use App\Nodes\Integrations\Typeform\TypeformGetMeNode;
use App\Nodes\Integrations\Typeform\TypeformGetResponseNode;
use App\Nodes\Integrations\Typeform\TypeformListFormsNode;
use App\Nodes\Integrations\Typeform\TypeformListResponsesNode;
use App\Nodes\Integrations\Typeform\TypeformListThemesNode;
use App\Nodes\Integrations\Typeform\TypeformListWebhooksNode;
use App\Nodes\Integrations\Typeform\TypeformListWorkspacesNode;
use App\Nodes\Integrations\Typeform\TypeformRenameWorkspaceNode;
use App\Nodes\Integrations\Typeform\TypeformUpdateFormNode;
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
            'google_forms_add_question' => GoogleFormsAddQuestionNode::class,
            'google_forms_delete_item' => GoogleFormsDeleteItemNode::class,
            'google_forms_update_form_info' => GoogleFormsUpdateFormInfoNode::class,
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
            'jira_add_attachment' => JiraAddAttachmentNode::class,
            'jira_add_comment' => JiraAddCommentNode::class,
            'jira_add_user_to_group' => JiraAddUserToGroupNode::class,
            'jira_add_user_to_issue' => JiraAddUserToIssueNode::class,
            'jira_create_customer_request' => JiraCreateCustomerRequestNode::class,
            'jira_create_group' => JiraCreateGroupNode::class,
            'jira_create_issue' => JiraCreateIssueNode::class,
            'jira_create_issue_link' => JiraCreateIssueLinkNode::class,
            'jira_create_project' => JiraCreateProjectNode::class,
            'jira_delete_comment' => JiraDeleteCommentNode::class,
            'jira_delete_issue' => JiraDeleteIssueNode::class,
            'jira_delete_issue_link' => JiraDeleteIssueLinkNode::class,
            'jira_delete_project' => JiraDeleteProjectNode::class,
            'jira_download_attachment' => JiraDownloadAttachmentNode::class,
            'jira_get_customer_request' => JiraGetCustomerRequestNode::class,
            'jira_get_edit_metadata' => JiraGetEditMetadataNode::class,
            'jira_get_issue' => JiraGetIssueNode::class,
            'jira_get_issue_links' => JiraGetIssueLinksNode::class,
            'jira_get_my_issues' => JiraGetMyIssuesNode::class,
            'jira_get_my_permissions' => JiraGetMyPermissionsNode::class,
            'jira_get_my_recent_activity' => JiraGetMyRecentActivityNode::class,
            'jira_get_myself' => JiraGetMyselfNode::class,
            'jira_get_project' => JiraGetProjectNode::class,
            'jira_get_request_type_fields' => JiraGetRequestTypeFieldsNode::class,
            'jira_get_request_types' => JiraGetRequestTypesNode::class,
            'jira_list_comments' => JiraListCommentsNode::class,
            'jira_list_customer_requests' => JiraListCustomerRequestsNode::class,
            'jira_list_fields' => JiraListFieldsNode::class,
            'jira_list_filters' => JiraListFiltersNode::class,
            'jira_list_groups' => JiraListGroupsNode::class,
            'jira_list_issue_link_types' => JiraListIssueLinkTypesNode::class,
            'jira_list_issue_types' => JiraListIssueTypesNode::class,
            'jira_list_issues' => JiraListIssuesNode::class,
            'jira_list_new_issues' => JiraListNewIssuesNode::class,
            'jira_list_priorities' => JiraListPrioritiesNode::class,
            'jira_list_projects' => JiraListProjectsNode::class,
            'jira_list_service_desks' => JiraListServiceDesksNode::class,
            'jira_list_transitions' => JiraListTransitionsNode::class,
            'jira_list_updated_issues' => JiraListUpdatedIssuesNode::class,
            'jira_list_users' => JiraListUsersNode::class,
            'jira_remove_user_from_group' => JiraRemoveUserFromGroupNode::class,
            'jira_search_issues' => JiraSearchIssuesNode::class,
            'jira_transition_issue' => JiraTransitionIssueNode::class,
            'jira_update_comment' => JiraUpdateCommentNode::class,
            'jira_update_issue' => JiraUpdateIssueNode::class,
            'jira_update_project' => JiraUpdateProjectNode::class,
            'linear_create_comment' => LinearCreateCommentNode::class,
            'linear_create_initiative' => LinearCreateInitiativeNode::class,
            'linear_create_issue' => LinearCreateIssueNode::class,
            'linear_create_issue_relation' => LinearCreateIssueRelationNode::class,
            'linear_create_project' => LinearCreateProjectNode::class,
            'linear_create_status_update' => LinearCreateStatusUpdateNode::class,
            'linear_delete_comment' => LinearDeleteCommentNode::class,
            'linear_delete_initiative' => LinearDeleteInitiativeNode::class,
            'linear_delete_issue' => LinearDeleteIssueNode::class,
            'linear_delete_issue_relation' => LinearDeleteIssueRelationNode::class,
            'linear_delete_project' => LinearDeleteProjectNode::class,
            'linear_delete_status_update' => LinearDeleteStatusUpdateNode::class,
            'linear_get_initiative' => LinearGetInitiativeNode::class,
            'linear_get_issue' => LinearGetIssueNode::class,
            'linear_get_me' => LinearGetMeNode::class,
            'linear_get_project' => LinearGetProjectNode::class,
            'linear_list_comments' => LinearListCommentsNode::class,
            'linear_list_cycles' => LinearListCyclesNode::class,
            'linear_list_initiatives' => LinearListInitiativesNode::class,
            'linear_list_issue_relations' => LinearListIssueRelationsNode::class,
            'linear_list_issues' => LinearListIssuesNode::class,
            'linear_list_labels' => LinearListLabelsNode::class,
            'linear_list_new_comments' => LinearListNewCommentsNode::class,
            'linear_list_new_issues' => LinearListNewIssuesNode::class,
            'linear_list_projects' => LinearListProjectsNode::class,
            'linear_list_status_updates' => LinearListStatusUpdatesNode::class,
            'linear_list_teams' => LinearListTeamsNode::class,
            'linear_list_updated_issues' => LinearListUpdatedIssuesNode::class,
            'linear_list_users' => LinearListUsersNode::class,
            'linear_list_workflow_states' => LinearListWorkflowStatesNode::class,
            'linear_search_issues' => LinearSearchIssuesNode::class,
            'linear_update_comment' => LinearUpdateCommentNode::class,
            'linear_update_initiative' => LinearUpdateInitiativeNode::class,
            'linear_update_issue' => LinearUpdateIssueNode::class,
            'linear_update_issue_relation' => LinearUpdateIssueRelationNode::class,
            'linear_update_project' => LinearUpdateProjectNode::class,
            'linear_update_status_update' => LinearUpdateStatusUpdateNode::class,
            'email_parse_inbound' => EmailParseInboundNode::class,
            'email_send' => EmailSendEmailNode::class,
            'email_send_to_many' => EmailSendToManyNode::class,
            'email_validate_address' => EmailValidateAddressNode::class,
            'telegram_answer_callback_query' => TelegramAnswerCallbackQueryNode::class,
            'telegram_approve_chat_join_request' => TelegramApproveChatJoinRequestNode::class,
            'telegram_ban_chat_member' => TelegramBanChatMemberNode::class,
            'telegram_bot_chat_status' => TelegramBotChatStatusNode::class,
            'telegram_copy_message' => TelegramCopyMessageNode::class,
            'telegram_create_chat_invite_link' => TelegramCreateChatInviteLinkNode::class,
            'telegram_decline_chat_join_request' => TelegramDeclineChatJoinRequestNode::class,
            'telegram_delete_message' => TelegramDeleteMessageNode::class,
            'telegram_delete_messages' => TelegramDeleteMessagesNode::class,
            'telegram_delete_my_commands' => TelegramDeleteMyCommandsNode::class,
            'telegram_edit_message_buttons' => TelegramEditMessageButtonsNode::class,
            'telegram_edit_message_caption' => TelegramEditMessageCaptionNode::class,
            'telegram_edit_message_text' => TelegramEditMessageTextNode::class,
            'telegram_edited_message' => TelegramEditedMessageNode::class,
            'telegram_forward_message' => TelegramForwardMessageNode::class,
            'telegram_get_chat_administrators' => TelegramGetChatAdministratorsNode::class,
            'telegram_get_chat_member_count' => TelegramGetChatMemberCountNode::class,
            'telegram_get_chat_member' => TelegramGetChatMemberNode::class,
            'telegram_get_chat' => TelegramGetChatNode::class,
            'telegram_get_file' => TelegramGetFileNode::class,
            'telegram_get_me' => TelegramGetMeNode::class,
            'telegram_get_my_commands' => TelegramGetMyCommandsNode::class,
            'telegram_get_webhook_info' => TelegramGetWebhookInfoNode::class,
            'telegram_leave_chat' => TelegramLeaveChatNode::class,
            'telegram_member_joined' => TelegramMemberJoinedNode::class,
            'telegram_member_left' => TelegramMemberLeftNode::class,
            'telegram_new_callback_query' => TelegramNewCallbackQueryNode::class,
            'telegram_new_channel_post' => TelegramNewChannelPostNode::class,
            'telegram_new_join_request' => TelegramNewJoinRequestNode::class,
            'telegram_new_message' => TelegramNewMessageNode::class,
            'telegram_new_poll_answer' => TelegramNewPollAnswerNode::class,
            'telegram_pin_chat_message' => TelegramPinChatMessageNode::class,
            'telegram_restrict_chat_member' => TelegramRestrictChatMemberNode::class,
            'telegram_send_animation' => TelegramSendAnimationNode::class,
            'telegram_send_audio' => TelegramSendAudioNode::class,
            'telegram_send_chat_action' => TelegramSendChatActionNode::class,
            'telegram_send_contact' => TelegramSendContactNode::class,
            'telegram_send_dice' => TelegramSendDiceNode::class,
            'telegram_send_document' => TelegramSendDocumentNode::class,
            'telegram_send_location' => TelegramSendLocationNode::class,
            'telegram_send_media_group' => TelegramSendMediaGroupNode::class,
            'telegram_send_message' => TelegramSendMessageNode::class,
            'telegram_send_photo' => TelegramSendPhotoNode::class,
            'telegram_send_poll' => TelegramSendPollNode::class,
            'telegram_send_sticker' => TelegramSendStickerNode::class,
            'telegram_send_venue' => TelegramSendVenueNode::class,
            'telegram_send_video' => TelegramSendVideoNode::class,
            'telegram_send_voice' => TelegramSendVoiceNode::class,
            'telegram_set_chat_description' => TelegramSetChatDescriptionNode::class,
            'telegram_set_chat_title' => TelegramSetChatTitleNode::class,
            'telegram_set_my_commands' => TelegramSetMyCommandsNode::class,
            'telegram_stop_poll' => TelegramStopPollNode::class,
            'telegram_unban_chat_member' => TelegramUnbanChatMemberNode::class,
            'telegram_unpin_all_chat_messages' => TelegramUnpinAllChatMessagesNode::class,
            'telegram_unpin_chat_message' => TelegramUnpinChatMessageNode::class,
            'discord_add_reaction' => DiscordAddReactionNode::class,
            'discord_add_role' => DiscordAddRoleNode::class,
            'discord_add_thread_member' => DiscordAddThreadMemberNode::class,
            'discord_archive_thread' => DiscordArchiveThreadNode::class,
            'discord_ban_member' => DiscordBanMemberNode::class,
            'discord_bulk_delete_messages' => DiscordBulkDeleteMessagesNode::class,
            'discord_clear_reactions' => DiscordClearReactionsNode::class,
            'discord_create_channel' => DiscordCreateChannelNode::class,
            'discord_create_invite' => DiscordCreateInviteNode::class,
            'discord_create_role' => DiscordCreateRoleNode::class,
            'discord_create_thread' => DiscordCreateThreadNode::class,
            'discord_delete_channel' => DiscordDeleteChannelNode::class,
            'discord_delete_command' => DiscordDeleteCommandNode::class,
            'discord_delete_invite' => DiscordDeleteInviteNode::class,
            'discord_delete_message' => DiscordDeleteMessageNode::class,
            'discord_delete_role' => DiscordDeleteRoleNode::class,
            'discord_edit_channel' => DiscordEditChannelNode::class,
            'discord_edit_message' => DiscordEditMessageNode::class,
            'discord_get_channel' => DiscordGetChannelNode::class,
            'discord_get_guild' => DiscordGetGuildNode::class,
            'discord_get_me' => DiscordGetMeNode::class,
            'discord_get_member' => DiscordGetMemberNode::class,
            'discord_get_message' => DiscordGetMessageNode::class,
            'discord_get_user' => DiscordGetUserNode::class,
            'discord_kick_member' => DiscordKickMemberNode::class,
            'discord_list_active_threads' => DiscordListActiveThreadsNode::class,
            'discord_list_bans' => DiscordListBansNode::class,
            'discord_list_channels' => DiscordListChannelsNode::class,
            'discord_list_commands' => DiscordListCommandsNode::class,
            'discord_list_guilds' => DiscordListGuildsNode::class,
            'discord_list_invites' => DiscordListInvitesNode::class,
            'discord_list_members' => DiscordListMembersNode::class,
            'discord_list_messages' => DiscordListMessagesNode::class,
            'discord_list_new_channels' => DiscordListNewChannelsNode::class,
            'discord_list_new_members' => DiscordListNewMembersNode::class,
            'discord_list_new_messages' => DiscordListNewMessagesNode::class,
            'discord_list_new_threads' => DiscordListNewThreadsNode::class,
            'discord_list_pinned_messages' => DiscordListPinnedMessagesNode::class,
            'discord_list_reactions' => DiscordListReactionsNode::class,
            'discord_list_roles' => DiscordListRolesNode::class,
            'discord_new_button_click' => DiscordNewButtonClickNode::class,
            'discord_new_slash_command' => DiscordNewSlashCommandNode::class,
            'discord_pin_message' => DiscordPinMessageNode::class,
            'discord_register_command' => DiscordRegisterCommandNode::class,
            'discord_remove_reaction' => DiscordRemoveReactionNode::class,
            'discord_remove_role' => DiscordRemoveRoleNode::class,
            'discord_remove_thread_member' => DiscordRemoveThreadMemberNode::class,
            'discord_respond_to_interaction' => DiscordRespondToInteractionNode::class,
            'discord_search_members' => DiscordSearchMembersNode::class,
            'discord_send_direct_message' => DiscordSendDirectMessageNode::class,
            'discord_send_message' => DiscordSendMessageNode::class,
            'discord_send_webhook_message' => DiscordSendWebhookMessageNode::class,
            'discord_set_nickname' => DiscordSetNicknameNode::class,
            'discord_timeout_member' => DiscordTimeoutMemberNode::class,
            'discord_unban_member' => DiscordUnbanMemberNode::class,
            'discord_unpin_message' => DiscordUnpinMessageNode::class,
            'twilio_check_verification' => TwilioCheckVerificationNode::class,
            'twilio_delete_message' => TwilioDeleteMessageNode::class,
            'twilio_delete_recording' => TwilioDeleteRecordingNode::class,
            'twilio_get_account' => TwilioGetAccountNode::class,
            'twilio_get_balance' => TwilioGetBalanceNode::class,
            'twilio_get_call' => TwilioGetCallNode::class,
            'twilio_get_message' => TwilioGetMessageNode::class,
            'twilio_list_calls' => TwilioListCallsNode::class,
            'twilio_list_failed_messages' => TwilioListFailedMessagesNode::class,
            'twilio_list_messages' => TwilioListMessagesNode::class,
            'twilio_list_new_calls' => TwilioListNewCallsNode::class,
            'twilio_list_new_incoming_messages' => TwilioListNewIncomingMessagesNode::class,
            'twilio_list_new_recordings' => TwilioListNewRecordingsNode::class,
            'twilio_list_phone_numbers' => TwilioListPhoneNumbersNode::class,
            'twilio_list_recordings' => TwilioListRecordingsNode::class,
            'twilio_list_verify_services' => TwilioListVerifyServicesNode::class,
            'twilio_lookup_phone_number' => TwilioLookupPhoneNumberNode::class,
            'twilio_make_call' => TwilioMakeCallNode::class,
            'twilio_redact_message' => TwilioRedactMessageNode::class,
            'twilio_send_sms' => TwilioSendSmsNode::class,
            'twilio_send_whatsapp' => TwilioSendWhatsAppNode::class,
            'twilio_start_verification' => TwilioStartVerificationNode::class,
            'twilio_update_call' => TwilioUpdateCallNode::class,
            'typeform_create_form' => TypeformCreateFormNode::class,
            'typeform_create_webhook' => TypeformCreateWebhookNode::class,
            'typeform_create_workspace' => TypeformCreateWorkspaceNode::class,
            'typeform_delete_form' => TypeformDeleteFormNode::class,
            'typeform_delete_responses' => TypeformDeleteResponsesNode::class,
            'typeform_delete_webhook' => TypeformDeleteWebhookNode::class,
            'typeform_delete_workspace' => TypeformDeleteWorkspaceNode::class,
            'typeform_get_form' => TypeformGetFormNode::class,
            'typeform_get_me' => TypeformGetMeNode::class,
            'typeform_get_response' => TypeformGetResponseNode::class,
            'typeform_list_forms' => TypeformListFormsNode::class,
            'typeform_list_responses' => TypeformListResponsesNode::class,
            'typeform_list_themes' => TypeformListThemesNode::class,
            'typeform_list_webhooks' => TypeformListWebhooksNode::class,
            'typeform_list_workspaces' => TypeformListWorkspacesNode::class,
            'typeform_rename_workspace' => TypeformRenameWorkspaceNode::class,
            'typeform_update_form' => TypeformUpdateFormNode::class,
            'mailchimp_add_note' => MailchimpAddNoteNode::class,
            'mailchimp_add_or_update_member' => MailchimpAddOrUpdateMemberNode::class,
            'mailchimp_add_tags' => MailchimpAddTagsNode::class,
            'mailchimp_archive_member' => MailchimpArchiveMemberNode::class,
            'mailchimp_check_send_readiness' => MailchimpCheckSendReadinessNode::class,
            'mailchimp_create_audience' => MailchimpCreateAudienceNode::class,
            'mailchimp_create_campaign' => MailchimpCreateCampaignNode::class,
            'mailchimp_create_segment' => MailchimpCreateSegmentNode::class,
            'mailchimp_delete_audience' => MailchimpDeleteAudienceNode::class,
            'mailchimp_delete_campaign' => MailchimpDeleteCampaignNode::class,
            'mailchimp_delete_member' => MailchimpDeleteMemberNode::class,
            'mailchimp_get_account' => MailchimpGetAccountNode::class,
            'mailchimp_get_audience' => MailchimpGetAudienceNode::class,
            'mailchimp_get_campaign' => MailchimpGetCampaignNode::class,
            'mailchimp_get_campaign_report' => MailchimpGetCampaignReportNode::class,
            'mailchimp_get_member_activity' => MailchimpGetMemberActivityNode::class,
            'mailchimp_get_member' => MailchimpGetMemberNode::class,
            'mailchimp_list_audiences' => MailchimpListAudiencesNode::class,
            'mailchimp_list_campaign_clicks' => MailchimpListCampaignClicksNode::class,
            'mailchimp_list_campaigns' => MailchimpListCampaignsNode::class,
            'mailchimp_list_cleaned_members' => MailchimpListCleanedMembersNode::class,
            'mailchimp_list_member_tags' => MailchimpListMemberTagsNode::class,
            'mailchimp_list_members' => MailchimpListMembersNode::class,
            'mailchimp_list_merge_fields' => MailchimpListMergeFieldsNode::class,
            'mailchimp_list_new_subscribers' => MailchimpListNewSubscribersNode::class,
            'mailchimp_list_notes' => MailchimpListNotesNode::class,
            'mailchimp_list_segments' => MailchimpListSegmentsNode::class,
            'mailchimp_list_templates' => MailchimpListTemplatesNode::class,
            'mailchimp_list_unsubscribes' => MailchimpListUnsubscribesNode::class,
            'mailchimp_list_updated_members' => MailchimpListUpdatedMembersNode::class,
            'mailchimp_remove_tags' => MailchimpRemoveTagsNode::class,
            'mailchimp_replicate_campaign' => MailchimpReplicateCampaignNode::class,
            'mailchimp_schedule_campaign' => MailchimpScheduleCampaignNode::class,
            'mailchimp_search_members' => MailchimpSearchMembersNode::class,
            'mailchimp_send_campaign' => MailchimpSendCampaignNode::class,
            'mailchimp_send_test_email' => MailchimpSendTestEmailNode::class,
            'mailchimp_set_campaign_content' => MailchimpSetCampaignContentNode::class,
            'mailchimp_unschedule_campaign' => MailchimpUnscheduleCampaignNode::class,
            'mailchimp_unsubscribe_member' => MailchimpUnsubscribeMemberNode::class,
            'mailchimp_update_member' => MailchimpUpdateMemberNode::class,
            'kit_add_subscriber_to_form' => KitAddSubscriberToFormNode::class,
            'kit_add_subscriber_to_sequence' => KitAddSubscriberToSequenceNode::class,
            'kit_create_broadcast' => KitCreateBroadcastNode::class,
            'kit_create_custom_field' => KitCreateCustomFieldNode::class,
            'kit_create_or_update_subscriber' => KitCreateOrUpdateSubscriberNode::class,
            'kit_create_purchase' => KitCreatePurchaseNode::class,
            'kit_create_tag' => KitCreateTagNode::class,
            'kit_delete_broadcast' => KitDeleteBroadcastNode::class,
            'kit_get_account' => KitGetAccountNode::class,
            'kit_get_broadcast' => KitGetBroadcastNode::class,
            'kit_get_broadcast_stats' => KitGetBroadcastStatsNode::class,
            'kit_get_subscriber' => KitGetSubscriberNode::class,
            'kit_list_bounced_subscribers' => KitListBouncedSubscribersNode::class,
            'kit_list_broadcasts' => KitListBroadcastsNode::class,
            'kit_list_custom_fields' => KitListCustomFieldsNode::class,
            'kit_list_email_templates' => KitListEmailTemplatesNode::class,
            'kit_list_form_subscribers' => KitListFormSubscribersNode::class,
            'kit_list_forms' => KitListFormsNode::class,
            'kit_list_purchases' => KitListPurchasesNode::class,
            'kit_list_sequences' => KitListSequencesNode::class,
            'kit_list_subscriber_tags' => KitListSubscriberTagsNode::class,
            'kit_list_subscribers' => KitListSubscribersNode::class,
            'kit_list_tagged_subscribers' => KitListTaggedSubscribersNode::class,
            'kit_list_tags' => KitListTagsNode::class,
            'kit_list_unsubscribes' => KitListUnsubscribesNode::class,
            'kit_tag_subscriber' => KitTagSubscriberNode::class,
            'kit_unsubscribe_subscriber' => KitUnsubscribeSubscriberNode::class,
            'kit_untag_subscriber' => KitUntagSubscriberNode::class,
            'kit_update_broadcast' => KitUpdateBroadcastNode::class,
            'kit_update_subscriber' => KitUpdateSubscriberNode::class,
            'activecampaign_add_note' => ActiveCampaignAddNoteNode::class,
            'activecampaign_add_tag' => ActiveCampaignAddTagNode::class,
            'activecampaign_add_to_automation' => ActiveCampaignAddToAutomationNode::class,
            'activecampaign_create_deal' => ActiveCampaignCreateDealNode::class,
            'activecampaign_create_list' => ActiveCampaignCreateListNode::class,
            'activecampaign_create_tag' => ActiveCampaignCreateTagNode::class,
            'activecampaign_delete_contact' => ActiveCampaignDeleteContactNode::class,
            'activecampaign_delete_deal' => ActiveCampaignDeleteDealNode::class,
            'activecampaign_get_account' => ActiveCampaignGetAccountNode::class,
            'activecampaign_get_contact' => ActiveCampaignGetContactNode::class,
            'activecampaign_get_deal' => ActiveCampaignGetDealNode::class,
            'activecampaign_list_automations' => ActiveCampaignListAutomationsNode::class,
            'activecampaign_list_contact_notes' => ActiveCampaignListContactNotesNode::class,
            'activecampaign_list_contact_tags' => ActiveCampaignListContactTagsNode::class,
            'activecampaign_list_contacts' => ActiveCampaignListContactsNode::class,
            'activecampaign_list_custom_fields' => ActiveCampaignListCustomFieldsNode::class,
            'activecampaign_list_deals' => ActiveCampaignListDealsNode::class,
            'activecampaign_list_lists' => ActiveCampaignListListsNode::class,
            'activecampaign_list_pipelines' => ActiveCampaignListPipelinesNode::class,
            'activecampaign_list_tags' => ActiveCampaignListTagsNode::class,
            'activecampaign_list_updated_contacts' => ActiveCampaignListUpdatedContactsNode::class,
            'activecampaign_list_users' => ActiveCampaignListUsersNode::class,
            'activecampaign_remove_from_automation' => ActiveCampaignRemoveFromAutomationNode::class,
            'activecampaign_remove_tag' => ActiveCampaignRemoveTagNode::class,
            'activecampaign_subscribe_to_list' => ActiveCampaignSubscribeToListNode::class,
            'activecampaign_sync_contact' => ActiveCampaignSyncContactNode::class,
            'activecampaign_unsubscribe_from_list' => ActiveCampaignUnsubscribeFromListNode::class,
            'activecampaign_update_contact' => ActiveCampaignUpdateContactNode::class,
            'activecampaign_update_deal' => ActiveCampaignUpdateDealNode::class,
            'klaviyo_add_profile_to_list' => KlaviyoAddProfileToListNode::class,
            'klaviyo_create_list' => KlaviyoCreateListNode::class,
            'klaviyo_delete_list' => KlaviyoDeleteListNode::class,
            'klaviyo_get_account' => KlaviyoGetAccountNode::class,
            'klaviyo_get_profile' => KlaviyoGetProfileNode::class,
            'klaviyo_list_campaigns' => KlaviyoListCampaignsNode::class,
            'klaviyo_list_events' => KlaviyoListEventsNode::class,
            'klaviyo_list_flows' => KlaviyoListFlowsNode::class,
            'klaviyo_list_list_profiles' => KlaviyoListListProfilesNode::class,
            'klaviyo_list_lists' => KlaviyoListListsNode::class,
            'klaviyo_list_metrics' => KlaviyoListMetricsNode::class,
            'klaviyo_list_profiles' => KlaviyoListProfilesNode::class,
            'klaviyo_list_segment_profiles' => KlaviyoListSegmentProfilesNode::class,
            'klaviyo_list_segments' => KlaviyoListSegmentsNode::class,
            'klaviyo_list_templates' => KlaviyoListTemplatesNode::class,
            'klaviyo_remove_profile_from_list' => KlaviyoRemoveProfileFromListNode::class,
            'klaviyo_set_flow_status' => KlaviyoSetFlowStatusNode::class,
            'klaviyo_subscribe_profile' => KlaviyoSubscribeProfileNode::class,
            'klaviyo_track_event' => KlaviyoTrackEventNode::class,
            'klaviyo_unsubscribe_profile' => KlaviyoUnsubscribeProfileNode::class,
            'klaviyo_update_profile' => KlaviyoUpdateProfileNode::class,
            'klaviyo_upsert_profile' => KlaviyoUpsertProfileNode::class,
            'brevo_add_contacts_to_list' => BrevoAddContactsToListNode::class,
            'brevo_create_list' => BrevoCreateListNode::class,
            'brevo_create_or_update_contact' => BrevoCreateOrUpdateContactNode::class,
            'brevo_delete_contact' => BrevoDeleteContactNode::class,
            'brevo_get_account' => BrevoGetAccountNode::class,
            'brevo_get_campaign' => BrevoGetCampaignNode::class,
            'brevo_get_contact' => BrevoGetContactNode::class,
            'brevo_list_attributes' => BrevoListAttributesNode::class,
            'brevo_list_campaigns' => BrevoListCampaignsNode::class,
            'brevo_list_contacts' => BrevoListContactsNode::class,
            'brevo_list_lists' => BrevoListListsNode::class,
            'brevo_list_senders' => BrevoListSendersNode::class,
            'brevo_list_templates' => BrevoListTemplatesNode::class,
            'brevo_remove_contacts_from_list' => BrevoRemoveContactsFromListNode::class,
            'brevo_send_campaign' => BrevoSendCampaignNode::class,
            'brevo_send_email' => BrevoSendEmailNode::class,
            'brevo_send_sms' => BrevoSendSmsNode::class,
            'brevo_track_event' => BrevoTrackEventNode::class,
            'brevo_update_contact' => BrevoUpdateContactNode::class,
            'brevo_list_updated_contacts' => BrevoListUpdatedContactsNode::class,
            'sendgrid_add_global_unsubscribe' => SendGridAddGlobalUnsubscribeNode::class,
            'sendgrid_create_list' => SendGridCreateListNode::class,
            'sendgrid_delete_contact' => SendGridDeleteContactNode::class,
            'sendgrid_delete_list' => SendGridDeleteListNode::class,
            'sendgrid_get_account' => SendGridGetAccountNode::class,
            'sendgrid_get_contact' => SendGridGetContactNode::class,
            'sendgrid_get_stats' => SendGridGetStatsNode::class,
            'sendgrid_list_blocks' => SendGridListBlocksNode::class,
            'sendgrid_list_bounces' => SendGridListBouncesNode::class,
            'sendgrid_list_invalid_emails' => SendGridListInvalidEmailsNode::class,
            'sendgrid_list_lists' => SendGridListListsNode::class,
            'sendgrid_list_senders' => SendGridListSendersNode::class,
            'sendgrid_list_spam_reports' => SendGridListSpamReportsNode::class,
            'sendgrid_list_templates' => SendGridListTemplatesNode::class,
            'sendgrid_list_unsubscribes' => SendGridListUnsubscribesNode::class,
            'sendgrid_remove_contact_from_list' => SendGridRemoveContactFromListNode::class,
            'sendgrid_remove_suppression' => SendGridRemoveSuppressionNode::class,
            'sendgrid_search_contacts' => SendGridSearchContactsNode::class,
            'sendgrid_send_email' => SendGridSendEmailNode::class,
            'sendgrid_upsert_contact' => SendGridUpsertContactNode::class,
            'resend_add_contact' => ResendAddContactNode::class,
            'resend_cancel_email' => ResendCancelEmailNode::class,
            'resend_create_audience' => ResendCreateAudienceNode::class,
            'resend_create_broadcast' => ResendCreateBroadcastNode::class,
            'resend_delete_audience' => ResendDeleteAudienceNode::class,
            'resend_delete_broadcast' => ResendDeleteBroadcastNode::class,
            'resend_delete_contact' => ResendDeleteContactNode::class,
            'resend_get_contact' => ResendGetContactNode::class,
            'resend_get_email' => ResendGetEmailNode::class,
            'resend_list_audiences' => ResendListAudiencesNode::class,
            'resend_list_broadcasts' => ResendListBroadcastsNode::class,
            'resend_list_contacts' => ResendListContactsNode::class,
            'resend_list_domains' => ResendListDomainsNode::class,
            'resend_list_emails' => ResendListEmailsNode::class,
            'resend_reschedule_email' => ResendRescheduleEmailNode::class,
            'resend_send_batch' => ResendSendBatchNode::class,
            'resend_send_broadcast' => ResendSendBroadcastNode::class,
            'resend_send_email' => ResendSendEmailNode::class,
            'resend_verify_domain' => ResendVerifyDomainNode::class,
            'postmark_activate_bounce' => PostmarkActivateBounceNode::class,
            'postmark_add_suppressions' => PostmarkAddSuppressionsNode::class,
            'postmark_delete_suppressions' => PostmarkDeleteSuppressionsNode::class,
            'postmark_get_bounce' => PostmarkGetBounceNode::class,
            'postmark_get_delivery_stats' => PostmarkGetDeliveryStatsNode::class,
            'postmark_get_inbound_message' => PostmarkGetInboundMessageNode::class,
            'postmark_get_outbound_message' => PostmarkGetOutboundMessageNode::class,
            'postmark_get_server' => PostmarkGetServerNode::class,
            'postmark_get_stats' => PostmarkGetStatsNode::class,
            'postmark_get_template' => PostmarkGetTemplateNode::class,
            'postmark_list_bounces' => PostmarkListBouncesNode::class,
            'postmark_list_clicks' => PostmarkListClicksNode::class,
            'postmark_list_inbound_messages' => PostmarkListInboundMessagesNode::class,
            'postmark_list_message_streams' => PostmarkListMessageStreamsNode::class,
            'postmark_list_opens' => PostmarkListOpensNode::class,
            'postmark_list_outbound_messages' => PostmarkListOutboundMessagesNode::class,
            'postmark_list_senders' => PostmarkListSendersNode::class,
            'postmark_list_spam_complaints' => PostmarkListSpamComplaintsNode::class,
            'postmark_list_suppressions' => PostmarkListSuppressionsNode::class,
            'postmark_list_templates' => PostmarkListTemplatesNode::class,
            'postmark_send_batch' => PostmarkSendBatchNode::class,
            'postmark_send_email' => PostmarkSendEmailNode::class,
            'postmark_send_template_email' => PostmarkSendTemplateEmailNode::class,
            'mailgun_add_member' => MailgunAddMemberNode::class,
            'mailgun_add_suppression' => MailgunAddSuppressionNode::class,
            'mailgun_create_mailing_list' => MailgunCreateMailingListNode::class,
            'mailgun_delete_mailing_list' => MailgunDeleteMailingListNode::class,
            'mailgun_get_domain' => MailgunGetDomainNode::class,
            'mailgun_get_member' => MailgunGetMemberNode::class,
            'mailgun_get_stats' => MailgunGetStatsNode::class,
            'mailgun_get_template' => MailgunGetTemplateNode::class,
            'mailgun_list_domains' => MailgunListDomainsNode::class,
            'mailgun_list_events' => MailgunListEventsNode::class,
            'mailgun_list_mailing_lists' => MailgunListMailingListsNode::class,
            'mailgun_list_members' => MailgunListMembersNode::class,
            'mailgun_list_suppressions' => MailgunListSuppressionsNode::class,
            'mailgun_list_templates' => MailgunListTemplatesNode::class,
            'mailgun_remove_member' => MailgunRemoveMemberNode::class,
            'mailgun_remove_suppression' => MailgunRemoveSuppressionNode::class,
            'mailgun_send_email' => MailgunSendEmailNode::class,
            'mailgun_update_member' => MailgunUpdateMemberNode::class,
            'mailgun_validate_email' => MailgunValidateEmailNode::class,
            'mailgun_verify_domain' => MailgunVerifyDomainNode::class,
            'gmail_archive_message' => GmailArchiveMessageNode::class,
            'gmail_batch_modify_messages' => GmailBatchModifyMessagesNode::class,
            'gmail_delete_draft' => GmailDeleteDraftNode::class,
            'gmail_delete_label' => GmailDeleteLabelNode::class,
            'gmail_forward_message' => GmailForwardMessageNode::class,
            'gmail_get_draft' => GmailGetDraftNode::class,
            'gmail_get_profile' => GmailGetProfileNode::class,
            'gmail_list_drafts' => GmailListDraftsNode::class,
            'gmail_mark_read' => GmailMarkReadNode::class,
            'gmail_trash_message' => GmailTrashMessageNode::class,
            'gmail_trash_thread' => GmailTrashThreadNode::class,
            'gmail_untrash_message' => GmailUntrashMessageNode::class,
            'gmail_update_draft' => GmailUpdateDraftNode::class,
            'gmail_update_label' => GmailUpdateLabelNode::class,
            'outlook_copy_message' => OutlookCopyMessageNode::class,
            'outlook_create_folder' => OutlookCreateFolderNode::class,
            'outlook_flag_message' => OutlookFlagMessageNode::class,
            'outlook_forward_message' => OutlookForwardMessageNode::class,
            'outlook_get_attachment' => OutlookGetAttachmentNode::class,
            'outlook_get_auto_reply' => OutlookGetAutoReplyNode::class,
            'outlook_get_profile' => OutlookGetProfileNode::class,
            'outlook_list_attachments' => OutlookListAttachmentsNode::class,
            'outlook_list_folders' => OutlookListFoldersNode::class,
            'outlook_mark_read' => OutlookMarkReadNode::class,
            'outlook_reply_all' => OutlookReplyAllNode::class,
            'outlook_send_draft' => OutlookSendDraftNode::class,
            'outlook_set_auto_reply' => OutlookSetAutoReplyNode::class,
            'outlook_set_categories' => OutlookSetCategoriesNode::class,
            'trello_add_attachment' => TrelloAddAttachmentNode::class,
            'trello_add_checklist_item' => TrelloAddChecklistItemNode::class,
            'trello_add_comment' => TrelloAddCommentNode::class,
            'trello_add_label' => TrelloAddLabelNode::class,
            'trello_add_member' => TrelloAddMemberNode::class,
            'trello_archive_all_cards' => TrelloArchiveAllCardsNode::class,
            'trello_create_board' => TrelloCreateBoardNode::class,
            'trello_create_card' => TrelloCreateCardNode::class,
            'trello_create_checklist' => TrelloCreateChecklistNode::class,
            'trello_create_label' => TrelloCreateLabelNode::class,
            'trello_create_list' => TrelloCreateListNode::class,
            'trello_delete_card' => TrelloDeleteCardNode::class,
            'trello_get_board' => TrelloGetBoardNode::class,
            'trello_get_card' => TrelloGetCardNode::class,
            'trello_get_member' => TrelloGetMemberNode::class,
            'trello_list_activity' => TrelloListActivityNode::class,
            'trello_list_boards' => TrelloListBoardsNode::class,
            'trello_list_cards' => TrelloListCardsNode::class,
            'trello_list_comments' => TrelloListCommentsNode::class,
            'trello_list_labels' => TrelloListLabelsNode::class,
            'trello_list_lists' => TrelloListListsNode::class,
            'trello_list_members' => TrelloListMembersNode::class,
            'trello_move_card' => TrelloMoveCardNode::class,
            'trello_remove_label' => TrelloRemoveLabelNode::class,
            'trello_remove_member' => TrelloRemoveMemberNode::class,
            'trello_search_cards' => TrelloSearchCardsNode::class,
            'trello_set_checklist_item' => TrelloSetChecklistItemNode::class,
            'trello_update_card' => TrelloUpdateCardNode::class,
            'trello_update_list' => TrelloUpdateListNode::class,
            'asana_add_comment' => AsanaAddCommentNode::class,
            'asana_add_tag' => AsanaAddTagNode::class,
            'asana_add_task_to_project' => AsanaAddTaskToProjectNode::class,
            'asana_complete_task' => AsanaCompleteTaskNode::class,
            'asana_create_project' => AsanaCreateProjectNode::class,
            'asana_create_section' => AsanaCreateSectionNode::class,
            'asana_create_subtask' => AsanaCreateSubtaskNode::class,
            'asana_create_tag' => AsanaCreateTagNode::class,
            'asana_create_task' => AsanaCreateTaskNode::class,
            'asana_delete_task' => AsanaDeleteTaskNode::class,
            'asana_get_me' => AsanaGetMeNode::class,
            'asana_get_project' => AsanaGetProjectNode::class,
            'asana_get_task' => AsanaGetTaskNode::class,
            'asana_list_comments' => AsanaListCommentsNode::class,
            'asana_list_completed_tasks' => AsanaListCompletedTasksNode::class,
            'asana_list_new_tasks' => AsanaListNewTasksNode::class,
            'asana_list_projects' => AsanaListProjectsNode::class,
            'asana_list_sections' => AsanaListSectionsNode::class,
            'asana_list_subtasks' => AsanaListSubtasksNode::class,
            'asana_list_tags' => AsanaListTagsNode::class,
            'asana_list_tasks' => AsanaListTasksNode::class,
            'asana_list_updated_tasks' => AsanaListUpdatedTasksNode::class,
            'asana_list_users' => AsanaListUsersNode::class,
            'asana_list_workspaces' => AsanaListWorkspacesNode::class,
            'asana_move_task_to_section' => AsanaMoveTaskToSectionNode::class,
            'asana_remove_tag' => AsanaRemoveTagNode::class,
            'asana_remove_task_from_project' => AsanaRemoveTaskFromProjectNode::class,
            'asana_search_tasks' => AsanaSearchTasksNode::class,
            'asana_update_project' => AsanaUpdateProjectNode::class,
            'asana_update_task' => AsanaUpdateTaskNode::class,
            'clickup_add_checklist_item' => ClickUpAddChecklistItemNode::class,
            'clickup_add_comment' => ClickUpAddCommentNode::class,
            'clickup_add_tag' => ClickUpAddTagNode::class,
            'clickup_create_checklist' => ClickUpCreateChecklistNode::class,
            'clickup_create_list' => ClickUpCreateListNode::class,
            'clickup_create_task' => ClickUpCreateTaskNode::class,
            'clickup_create_time_entry' => ClickUpCreateTimeEntryNode::class,
            'clickup_delete_task' => ClickUpDeleteTaskNode::class,
            'clickup_filter_tasks' => ClickUpFilterTasksNode::class,
            'clickup_get_list' => ClickUpGetListNode::class,
            'clickup_get_me' => ClickUpGetMeNode::class,
            'clickup_get_task' => ClickUpGetTaskNode::class,
            'clickup_list_comments' => ClickUpListCommentsNode::class,
            'clickup_list_custom_fields' => ClickUpListCustomFieldsNode::class,
            'clickup_list_folders' => ClickUpListFoldersNode::class,
            'clickup_list_lists' => ClickUpListListsNode::class,
            'clickup_list_new_tasks' => ClickUpListNewTasksNode::class,
            'clickup_list_spaces' => ClickUpListSpacesNode::class,
            'clickup_list_tasks' => ClickUpListTasksNode::class,
            'clickup_list_time_entries' => ClickUpListTimeEntriesNode::class,
            'clickup_list_updated_tasks' => ClickUpListUpdatedTasksNode::class,
            'clickup_list_workspaces' => ClickUpListWorkspacesNode::class,
            'clickup_remove_tag' => ClickUpRemoveTagNode::class,
            'clickup_set_custom_field' => ClickUpSetCustomFieldNode::class,
            'clickup_update_task' => ClickUpUpdateTaskNode::class,
            'airtable_add_comment' => AirtableAddCommentNode::class,
            'airtable_create_record' => AirtableCreateRecordNode::class,
            'airtable_create_records' => AirtableCreateRecordsNode::class,
            'airtable_delete_record' => AirtableDeleteRecordNode::class,
            'airtable_find_record' => AirtableFindRecordNode::class,
            'airtable_get_me' => AirtableGetMeNode::class,
            'airtable_get_record' => AirtableGetRecordNode::class,
            'airtable_list_bases' => AirtableListBasesNode::class,
            'airtable_list_comments' => AirtableListCommentsNode::class,
            'airtable_list_new_records' => AirtableListNewRecordsNode::class,
            'airtable_list_records' => AirtableListRecordsNode::class,
            'airtable_list_tables' => AirtableListTablesNode::class,
            'airtable_list_updated_records' => AirtableListUpdatedRecordsNode::class,
            'airtable_update_record' => AirtableUpdateRecordNode::class,
            'airtable_upsert_record' => AirtableUpsertRecordNode::class,
        ];
    }
}
