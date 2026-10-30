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
use App\Nodes\Integrations\Ahrefs\AhrefsApiRequestNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscPerformanceHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscPositionsHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscPagesHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscPerformanceByDeviceNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscMetricsByCountryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscCtrByPositionNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscPerformanceByPositionNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscKeywordHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscKeywordsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscPageHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscPagesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscAnonymousQueriesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSocialChannelsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSocialAuthorsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSocialPostsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSocialActivityHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSocialChannelMetricsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSocialPostMetricsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSocialCreatePostNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSocialUpdatePostNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSocialDeletePostNode;
use App\Nodes\Integrations\Ahrefs\AhrefsContentTopicsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsContentGapNode;
use App\Nodes\Integrations\Ahrefs\AhrefsLinkIntersectDomainsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsLinkIntersectPagesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsAddBrandRadarPromptsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsAddKeywordListItemsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsAddProjectCompetitorsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsAddProjectKeywordTagsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsAddProjectKeywordsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsAiResponsesCountNode;
use App\Nodes\Integrations\Ahrefs\AhrefsAnchorsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBacklinksNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBacklinksStatsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBatchAnalysisNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBestPagesByBacklinksNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBestPagesByInternalLinksNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarAiResponsesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarCitationsHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarCitationsOverviewNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarCitedDomainsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarCitedPagesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarImpressionsHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarImpressionsOverviewNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarMentionsHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarMentionsOverviewNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarSovHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarSovOverviewNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarTopicsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarWebPagesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrokenBacklinksNode;
use App\Nodes\Integrations\Ahrefs\AhrefsCrawledPagesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsCrawlerIpRangesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsCrawlerIpsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsCreateBrandRadarReportNode;
use App\Nodes\Integrations\Ahrefs\AhrefsCreateKeywordListNode;
use App\Nodes\Integrations\Ahrefs\AhrefsCreateProjectsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDeleteBrandRadarPromptsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDeleteKeywordListItemsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDeleteKeywordListsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDeleteProjectCompetitorsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDeleteProjectKeywordTagsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDeleteProjectKeywordsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDeleteProjectsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDomainRatingFreeNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDomainRatingHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDomainRatingNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDomainRatingTopDomainsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsKeywordVolumeByCountryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsKeywordVolumeHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsKeywordsHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsKeywordsOverviewNode;
use App\Nodes\Integrations\Ahrefs\AhrefsLimitsAndUsageNode;
use App\Nodes\Integrations\Ahrefs\AhrefsLinkedDomainsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListBrandRadarPromptsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListBrandRadarReportsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListKeywordListItemsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListKeywordListsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListLocationsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListLostBacklinksNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListLostKeywordsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListLostReferringDomainsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListNewBacklinksNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListNewKeywordsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListNewReferringDomainsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListProjectCompetitorsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListProjectKeywordsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListProjectsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListRankChangesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsMatchingTermsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsMetricsByCountryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsMetricsHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsMetricsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsOrganicCompetitorsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsOrganicKeywordsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsOutgoingExternalAnchorsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsOutgoingInternalAnchorsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsOutlinksStatsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsPagesByTrafficNode;
use App\Nodes\Integrations\Ahrefs\AhrefsPagesHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsPaidPagesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsRankTrackerCompetitorsDomainsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsRankTrackerCompetitorsOverviewNode;
use App\Nodes\Integrations\Ahrefs\AhrefsRankTrackerCompetitorsPagesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsRankTrackerCompetitorsStatsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsRankTrackerOverviewNode;
use App\Nodes\Integrations\Ahrefs\AhrefsRankTrackerSerpOverviewNode;
use App\Nodes\Integrations\Ahrefs\AhrefsRefdomainsHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsReferringDomainsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsRelatedTermsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSearchSuggestionsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSerpOverviewNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSiteAuditIssuesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSiteAuditPageContentNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSiteAuditPageExplorerNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSiteAuditProjectsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsTopPagesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsTotalSearchVolumeHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsUpdateBrandRadarPromptsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsUpdateBrandRadarReportNode;
use App\Nodes\Integrations\Ahrefs\AhrefsUpdateProjectNode;
use App\Nodes\Integrations\Ahrefs\AhrefsUrlRatingHistoryNode;
use App\Nodes\Integrations\Ahrefs\AhrefsWebAnalyticsBreakdownChartNode;
use App\Nodes\Integrations\Ahrefs\AhrefsWebAnalyticsBreakdownNode;
use App\Nodes\Integrations\Ahrefs\AhrefsWebAnalyticsChartNode;
use App\Nodes\Integrations\Ahrefs\AhrefsWebAnalyticsStatsNode;
use App\Nodes\Integrations\Excel\ExcelApiRequestNode;
use App\Nodes\Integrations\Excel\ExcelAddChartNode;
use App\Nodes\Integrations\Excel\ExcelAddNameNode;
use App\Nodes\Integrations\Excel\ExcelAddRowNode;
use App\Nodes\Integrations\Excel\ExcelAddRowsNode;
use App\Nodes\Integrations\Excel\ExcelAddTableColumnNode;
use App\Nodes\Integrations\Excel\ExcelAddTableNode;
use App\Nodes\Integrations\Excel\ExcelAddTableRowNode;
use App\Nodes\Integrations\Excel\ExcelAddWorksheetNode;
use App\Nodes\Integrations\Excel\ExcelCalculateWorkbookNode;
use App\Nodes\Integrations\Excel\ExcelCallFunctionNode;
use App\Nodes\Integrations\Excel\ExcelClearColumnNode;
use App\Nodes\Integrations\Excel\ExcelClearRangeNode;
use App\Nodes\Integrations\Excel\ExcelClearRowNode;
use App\Nodes\Integrations\Excel\ExcelClearTableFiltersNode;
use App\Nodes\Integrations\Excel\ExcelConvertTableToRangeNode;
use App\Nodes\Integrations\Excel\ExcelCopyWorkbookNode;
use App\Nodes\Integrations\Excel\ExcelCreateWorkbookNode;
use App\Nodes\Integrations\Excel\ExcelDeleteChartNode;
use App\Nodes\Integrations\Excel\ExcelDeleteRangeNode;
use App\Nodes\Integrations\Excel\ExcelDeleteRowNode;
use App\Nodes\Integrations\Excel\ExcelDeleteTableColumnNode;
use App\Nodes\Integrations\Excel\ExcelDeleteTableNode;
use App\Nodes\Integrations\Excel\ExcelDeleteTableRowNode;
use App\Nodes\Integrations\Excel\ExcelDeleteWorkbookNode;
use App\Nodes\Integrations\Excel\ExcelDeleteWorksheetNode;
use App\Nodes\Integrations\Excel\ExcelDownloadWorkbookNode;
use App\Nodes\Integrations\Excel\ExcelFilterTableColumnNode;
use App\Nodes\Integrations\Excel\ExcelFindOrCreateRowNode;
use App\Nodes\Integrations\Excel\ExcelFindOrCreateWorksheetNode;
use App\Nodes\Integrations\Excel\ExcelFindRowNode;
use App\Nodes\Integrations\Excel\ExcelFindTableRowNode;
use App\Nodes\Integrations\Excel\ExcelFindWorksheetNode;
use App\Nodes\Integrations\Excel\ExcelFormatRangeNode;
use App\Nodes\Integrations\Excel\ExcelGetCellNode;
use App\Nodes\Integrations\Excel\ExcelGetChartImageNode;
use App\Nodes\Integrations\Excel\ExcelGetChartNode;
use App\Nodes\Integrations\Excel\ExcelGetColumnsNode;
use App\Nodes\Integrations\Excel\ExcelGetNameNode;
use App\Nodes\Integrations\Excel\ExcelGetNameRangeNode;
use App\Nodes\Integrations\Excel\ExcelGetRangeNode;
use App\Nodes\Integrations\Excel\ExcelGetRowNode;
use App\Nodes\Integrations\Excel\ExcelGetTableNode;
use App\Nodes\Integrations\Excel\ExcelGetTableRangeNode;
use App\Nodes\Integrations\Excel\ExcelGetTableRowNode;
use App\Nodes\Integrations\Excel\ExcelGetWorkbookNode;
use App\Nodes\Integrations\Excel\ExcelGetWorksheetNode;
use App\Nodes\Integrations\Excel\ExcelInsertRangeNode;
use App\Nodes\Integrations\Excel\ExcelListChartsNode;
use App\Nodes\Integrations\Excel\ExcelListCommentsNode;
use App\Nodes\Integrations\Excel\ExcelListDrivesNode;
use App\Nodes\Integrations\Excel\ExcelListFoldersNode;
use App\Nodes\Integrations\Excel\ExcelListNamesNode;
use App\Nodes\Integrations\Excel\ExcelListPivotTablesNode;
use App\Nodes\Integrations\Excel\ExcelListRowsNode;
use App\Nodes\Integrations\Excel\ExcelListTableColumnsNode;
use App\Nodes\Integrations\Excel\ExcelListTableRowsNode;
use App\Nodes\Integrations\Excel\ExcelListTablesNode;
use App\Nodes\Integrations\Excel\ExcelListUpdatedRowsNode;
use App\Nodes\Integrations\Excel\ExcelListUpdatedTableRowsNode;
use App\Nodes\Integrations\Excel\ExcelListUpdatedWorkbooksNode;
use App\Nodes\Integrations\Excel\ExcelListWorkbooksNode;
use App\Nodes\Integrations\Excel\ExcelListWorksheetsNode;
use App\Nodes\Integrations\Excel\ExcelMergeRangeNode;
use App\Nodes\Integrations\Excel\ExcelProtectWorksheetNode;
use App\Nodes\Integrations\Excel\ExcelReapplyTableFiltersNode;
use App\Nodes\Integrations\Excel\ExcelRefreshPivotTableNode;
use App\Nodes\Integrations\Excel\ExcelRenameWorkbookNode;
use App\Nodes\Integrations\Excel\ExcelSearchSitesNode;
use App\Nodes\Integrations\Excel\ExcelSearchWorkbooksNode;
use App\Nodes\Integrations\Excel\ExcelSetChartDataNode;
use App\Nodes\Integrations\Excel\ExcelSortRangeNode;
use App\Nodes\Integrations\Excel\ExcelSortTableNode;
use App\Nodes\Integrations\Excel\ExcelUnmergeRangeNode;
use App\Nodes\Integrations\Excel\ExcelUnprotectWorksheetNode;
use App\Nodes\Integrations\Excel\ExcelUpdateCellNode;
use App\Nodes\Integrations\Excel\ExcelUpdateChartNode;
use App\Nodes\Integrations\Excel\ExcelUpdateNameNode;
use App\Nodes\Integrations\Excel\ExcelUpdateRangeNode;
use App\Nodes\Integrations\Excel\ExcelUpdateRowNode;
use App\Nodes\Integrations\Excel\ExcelUpdateTableColumnNode;
use App\Nodes\Integrations\Excel\ExcelUpdateTableNode;
use App\Nodes\Integrations\Excel\ExcelUpdateTableRowNode;
use App\Nodes\Integrations\Excel\ExcelUpdateWorksheetNode;
use App\Nodes\Integrations\Excel\ExcelWatchCellNode;
use App\Nodes\Integrations\Semrush\SemrushApiRequestNode;
use App\Nodes\Integrations\Semrush\SemrushAddTrackingCompetitorsNode;
use App\Nodes\Integrations\Semrush\SemrushAddTrackingKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushAddTrackingTagsNode;
use App\Nodes\Integrations\Semrush\SemrushAgeAndSexDistributionNode;
use App\Nodes\Integrations\Semrush\SemrushAudienceInsightsNode;
use App\Nodes\Integrations\Semrush\SemrushAudienceInterestsNode;
use App\Nodes\Integrations\Semrush\SemrushAuthorityScoreProfileNode;
use App\Nodes\Integrations\Semrush\SemrushBacklinkAnchorsNode;
use App\Nodes\Integrations\Semrush\SemrushBacklinkCompetitorsNode;
use App\Nodes\Integrations\Semrush\SemrushBacklinksBatchComparisonNode;
use App\Nodes\Integrations\Semrush\SemrushBacklinksComparisonByDomainNode;
use App\Nodes\Integrations\Semrush\SemrushBacklinksHistoryNode;
use App\Nodes\Integrations\Semrush\SemrushBacklinksNode;
use App\Nodes\Integrations\Semrush\SemrushBacklinksOverviewNode;
use App\Nodes\Integrations\Semrush\SemrushBacklinksTldDistributionNode;
use App\Nodes\Integrations\Semrush\SemrushBroadMatchKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushCompareDomainsNode;
use App\Nodes\Integrations\Semrush\SemrushCreateProjectNode;
use App\Nodes\Integrations\Semrush\SemrushCreateSiteAuditCampaignNode;
use App\Nodes\Integrations\Semrush\SemrushCreateTrackingCampaignNode;
use App\Nodes\Integrations\Semrush\SemrushDailyTrafficNode;
use App\Nodes\Integrations\Semrush\SemrushDataAccuracyNode;
use App\Nodes\Integrations\Semrush\SemrushDeleteProjectNode;
use App\Nodes\Integrations\Semrush\SemrushDisableTrackingEmailsNode;
use App\Nodes\Integrations\Semrush\SemrushDomainAdCopiesNode;
use App\Nodes\Integrations\Semrush\SemrushDomainAdHistoryNode;
use App\Nodes\Integrations\Semrush\SemrushDomainCategoriesNode;
use App\Nodes\Integrations\Semrush\SemrushDomainOrganicCompetitorsNode;
use App\Nodes\Integrations\Semrush\SemrushDomainOrganicKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushDomainOrganicPagesNode;
use App\Nodes\Integrations\Semrush\SemrushDomainOrganicSubdomainsNode;
use App\Nodes\Integrations\Semrush\SemrushDomainOverviewAllDatabasesNode;
use App\Nodes\Integrations\Semrush\SemrushDomainOverviewHistoryNode;
use App\Nodes\Integrations\Semrush\SemrushDomainOverviewNode;
use App\Nodes\Integrations\Semrush\SemrushDomainPaidCompetitorsNode;
use App\Nodes\Integrations\Semrush\SemrushDomainPaidKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushDomainPlaCompetitorsNode;
use App\Nodes\Integrations\Semrush\SemrushDomainPlaCopiesNode;
use App\Nodes\Integrations\Semrush\SemrushDomainPlaKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushEducationDistributionNode;
use App\Nodes\Integrations\Semrush\SemrushEnableSiteAuditNode;
use App\Nodes\Integrations\Semrush\SemrushEnableTrackingEmailsNode;
use App\Nodes\Integrations\Semrush\SemrushFindSiteAuditPageNode;
use App\Nodes\Integrations\Semrush\SemrushGeoDistributionNode;
use App\Nodes\Integrations\Semrush\SemrushGetApiUnitsNode;
use App\Nodes\Integrations\Semrush\SemrushGetProjectNode;
use App\Nodes\Integrations\Semrush\SemrushGetSiteAuditHistoryNode;
use App\Nodes\Integrations\Semrush\SemrushGetSiteAuditIssueNode;
use App\Nodes\Integrations\Semrush\SemrushGetSiteAuditNode;
use App\Nodes\Integrations\Semrush\SemrushGetSiteAuditPageNode;
use App\Nodes\Integrations\Semrush\SemrushGetSiteAuditSnapshotNode;
use App\Nodes\Integrations\Semrush\SemrushGetTrendsLimitsNode;
use App\Nodes\Integrations\Semrush\SemrushHouseholdDistributionNode;
use App\Nodes\Integrations\Semrush\SemrushIncomeDistributionNode;
use App\Nodes\Integrations\Semrush\SemrushIndexedPagesNode;
use App\Nodes\Integrations\Semrush\SemrushIndustryCategoriesNode;
use App\Nodes\Integrations\Semrush\SemrushKeywordAdsHistoryNode;
use App\Nodes\Integrations\Semrush\SemrushKeywordDifficultyNode;
use App\Nodes\Integrations\Semrush\SemrushKeywordOrganicResultsNode;
use App\Nodes\Integrations\Semrush\SemrushKeywordOverviewAllDatabasesNode;
use App\Nodes\Integrations\Semrush\SemrushKeywordOverviewNode;
use App\Nodes\Integrations\Semrush\SemrushKeywordPaidResultsNode;
use App\Nodes\Integrations\Semrush\SemrushKeywordQuestionsNode;
use App\Nodes\Integrations\Semrush\SemrushKeywordsBatchOverviewNode;
use App\Nodes\Integrations\Semrush\SemrushListLostBacklinksNode;
use App\Nodes\Integrations\Semrush\SemrushListLostKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushListNewBacklinksNode;
use App\Nodes\Integrations\Semrush\SemrushListNewKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushListNewReferringDomainsNode;
use App\Nodes\Integrations\Semrush\SemrushListProjectsNode;
use App\Nodes\Integrations\Semrush\SemrushListSiteAuditIssueTypesNode;
use App\Nodes\Integrations\Semrush\SemrushListSiteAuditSnapshotsNode;
use App\Nodes\Integrations\Semrush\SemrushListTrackingCampaignsNode;
use App\Nodes\Integrations\Semrush\SemrushListTrackingCompetitorsNode;
use App\Nodes\Integrations\Semrush\SemrushOccupationDistributionNode;
use App\Nodes\Integrations\Semrush\SemrushPurchaseConversionNode;
use App\Nodes\Integrations\Semrush\SemrushReferringCategoriesProfileNode;
use App\Nodes\Integrations\Semrush\SemrushReferringDomainsByCountryNode;
use App\Nodes\Integrations\Semrush\SemrushReferringDomainsNode;
use App\Nodes\Integrations\Semrush\SemrushReferringIpsNode;
use App\Nodes\Integrations\Semrush\SemrushRelatedKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushRemoveTrackingCompetitorsNode;
use App\Nodes\Integrations\Semrush\SemrushRemoveTrackingKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushRemoveTrackingTagsNode;
use App\Nodes\Integrations\Semrush\SemrushRunSiteAuditNode;
use App\Nodes\Integrations\Semrush\SemrushSearchTrackingLocationsNode;
use App\Nodes\Integrations\Semrush\SemrushSemrushRankNode;
use App\Nodes\Integrations\Semrush\SemrushSocialMediaNode;
use App\Nodes\Integrations\Semrush\SemrushSubdomainAdCopiesNode;
use App\Nodes\Integrations\Semrush\SemrushSubdomainOrganicKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushSubdomainOrganicPagesNode;
use App\Nodes\Integrations\Semrush\SemrushSubdomainOverviewAllDatabasesNode;
use App\Nodes\Integrations\Semrush\SemrushSubdomainOverviewHistoryNode;
use App\Nodes\Integrations\Semrush\SemrushSubdomainOverviewNode;
use App\Nodes\Integrations\Semrush\SemrushSubdomainPaidKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushSubfolderAdCopiesNode;
use App\Nodes\Integrations\Semrush\SemrushSubfolderOrganicKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushSubfolderOrganicPagesNode;
use App\Nodes\Integrations\Semrush\SemrushSubfolderOverviewAllDatabasesNode;
use App\Nodes\Integrations\Semrush\SemrushSubfolderOverviewHistoryNode;
use App\Nodes\Integrations\Semrush\SemrushSubfolderOverviewNode;
use App\Nodes\Integrations\Semrush\SemrushSubfolderPaidKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushTopPagesNode;
use App\Nodes\Integrations\Semrush\SemrushTrackingAdwordsCompetitorsNode;
use App\Nodes\Integrations\Semrush\SemrushTrackingAdwordsLandingPagesNode;
use App\Nodes\Integrations\Semrush\SemrushTrackingAdwordsOverviewNode;
use App\Nodes\Integrations\Semrush\SemrushTrackingAdwordsPositionsNode;
use App\Nodes\Integrations\Semrush\SemrushTrackingAdwordsVisibilityNode;
use App\Nodes\Integrations\Semrush\SemrushTrackingCampaignDatesNode;
use App\Nodes\Integrations\Semrush\SemrushTrackingOrganicCompetitorsNode;
use App\Nodes\Integrations\Semrush\SemrushTrackingOrganicLandingPagesNode;
use App\Nodes\Integrations\Semrush\SemrushTrackingOrganicOverviewNode;
use App\Nodes\Integrations\Semrush\SemrushTrackingOrganicPositionsNode;
use App\Nodes\Integrations\Semrush\SemrushTrackingOrganicVisibilityNode;
use App\Nodes\Integrations\Semrush\SemrushTrafficDestinationsNode;
use App\Nodes\Integrations\Semrush\SemrushTrafficRankNode;
use App\Nodes\Integrations\Semrush\SemrushTrafficSourcesNode;
use App\Nodes\Integrations\Semrush\SemrushTrafficSummaryNode;
use App\Nodes\Integrations\Semrush\SemrushTrendsSubdomainsNode;
use App\Nodes\Integrations\Semrush\SemrushTrendsSubfoldersNode;
use App\Nodes\Integrations\Semrush\SemrushUpdateProjectNode;
use App\Nodes\Integrations\Semrush\SemrushUpdateSiteAuditNode;
use App\Nodes\Integrations\Semrush\SemrushUrlOrganicKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushUrlOverviewAllDatabasesNode;
use App\Nodes\Integrations\Semrush\SemrushUrlOverviewHistoryNode;
use App\Nodes\Integrations\Semrush\SemrushUrlOverviewNode;
use App\Nodes\Integrations\Semrush\SemrushUrlPaidKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushWeeklyTrafficNode;
use App\Nodes\Integrations\Semrush\SemrushWinnersAndLosersNode;
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
use App\Nodes\Integrations\Apollo\ApolloApiRequestNode;
use App\Nodes\Integrations\Apollo\ApolloActivateSequenceNode;
use App\Nodes\Integrations\Apollo\ApolloAddContactsToSequenceNode;
use App\Nodes\Integrations\Apollo\ApolloAddToListNode;
use App\Nodes\Integrations\Apollo\ApolloArchiveSequenceNode;
use App\Nodes\Integrations\Apollo\ApolloBulkCreateAccountsNode;
use App\Nodes\Integrations\Apollo\ApolloBulkCreateContactsNode;
use App\Nodes\Integrations\Apollo\ApolloBulkCreateTasksNode;
use App\Nodes\Integrations\Apollo\ApolloBulkEnrichOrganizationsNode;
use App\Nodes\Integrations\Apollo\ApolloBulkEnrichPeopleNode;
use App\Nodes\Integrations\Apollo\ApolloBulkUpdateAccountsNode;
use App\Nodes\Integrations\Apollo\ApolloBulkUpdateContactsNode;
use App\Nodes\Integrations\Apollo\ApolloCheckEmailStatusNode;
use App\Nodes\Integrations\Apollo\ApolloCompleteTaskNode;
use App\Nodes\Integrations\Apollo\ApolloCreateAccountNode;
use App\Nodes\Integrations\Apollo\ApolloCreateCallNode;
use App\Nodes\Integrations\Apollo\ApolloCreateContactNode;
use App\Nodes\Integrations\Apollo\ApolloCreateCustomFieldNode;
use App\Nodes\Integrations\Apollo\ApolloCreateDealNode;
use App\Nodes\Integrations\Apollo\ApolloCreateEmailDraftNode;
use App\Nodes\Integrations\Apollo\ApolloCreateListNode;
use App\Nodes\Integrations\Apollo\ApolloCreateSequenceNode;
use App\Nodes\Integrations\Apollo\ApolloCreateTaskNode;
use App\Nodes\Integrations\Apollo\ApolloDeactivateSequenceNode;
use App\Nodes\Integrations\Apollo\ApolloEnrichOrganizationNode;
use App\Nodes\Integrations\Apollo\ApolloEnrichPersonNode;
use App\Nodes\Integrations\Apollo\ApolloExportConversationsNode;
use App\Nodes\Integrations\Apollo\ApolloFindContactNode;
use App\Nodes\Integrations\Apollo\ApolloFindOrCreateContactNode;
use App\Nodes\Integrations\Apollo\ApolloGetAccountNode;
use App\Nodes\Integrations\Apollo\ApolloGetApiUsageNode;
use App\Nodes\Integrations\Apollo\ApolloGetContactNode;
use App\Nodes\Integrations\Apollo\ApolloGetContactSequenceActivityNode;
use App\Nodes\Integrations\Apollo\ApolloGetConversationExportNode;
use App\Nodes\Integrations\Apollo\ApolloGetConversationNode;
use App\Nodes\Integrations\Apollo\ApolloGetCreditUsageNode;
use App\Nodes\Integrations\Apollo\ApolloGetCurrentUserNode;
use App\Nodes\Integrations\Apollo\ApolloGetDealNode;
use App\Nodes\Integrations\Apollo\ApolloGetEmailContentNode;
use App\Nodes\Integrations\Apollo\ApolloGetEmailStatsNode;
use App\Nodes\Integrations\Apollo\ApolloGetOrganizationNode;
use App\Nodes\Integrations\Apollo\ApolloGetPersonNode;
use App\Nodes\Integrations\Apollo\ApolloGetTaskNode;
use App\Nodes\Integrations\Apollo\ApolloGetWebhookResultNode;
use App\Nodes\Integrations\Apollo\ApolloListAccountStagesNode;
use App\Nodes\Integrations\Apollo\ApolloListChangedAccountsNode;
use App\Nodes\Integrations\Apollo\ApolloListChangedContactsNode;
use App\Nodes\Integrations\Apollo\ApolloListChangedDealsNode;
use App\Nodes\Integrations\Apollo\ApolloListContactDealsNode;
use App\Nodes\Integrations\Apollo\ApolloListContactStagesNode;
use App\Nodes\Integrations\Apollo\ApolloListCustomFieldsNode;
use App\Nodes\Integrations\Apollo\ApolloListDealStagesNode;
use App\Nodes\Integrations\Apollo\ApolloListDealsNode;
use App\Nodes\Integrations\Apollo\ApolloListEmailAccountsNode;
use App\Nodes\Integrations\Apollo\ApolloListEmailSchedulesNode;
use App\Nodes\Integrations\Apollo\ApolloListFieldsNode;
use App\Nodes\Integrations\Apollo\ApolloListJobPostingsNode;
use App\Nodes\Integrations\Apollo\ApolloListListsNode;
use App\Nodes\Integrations\Apollo\ApolloListNewAccountsNode;
use App\Nodes\Integrations\Apollo\ApolloListNewContactsNode;
use App\Nodes\Integrations\Apollo\ApolloListNewDealsNode;
use App\Nodes\Integrations\Apollo\ApolloListNotesNode;
use App\Nodes\Integrations\Apollo\ApolloListUsersNode;
use App\Nodes\Integrations\Apollo\ApolloPollAssistantTaskNode;
use App\Nodes\Integrations\Apollo\ApolloQueryReportNode;
use App\Nodes\Integrations\Apollo\ApolloRemoveFromListNode;
use App\Nodes\Integrations\Apollo\ApolloRunAssistantTaskNode;
use App\Nodes\Integrations\Apollo\ApolloSearchAccountsNode;
use App\Nodes\Integrations\Apollo\ApolloSearchCallsNode;
use App\Nodes\Integrations\Apollo\ApolloSearchContactsNode;
use App\Nodes\Integrations\Apollo\ApolloSearchConversationsNode;
use App\Nodes\Integrations\Apollo\ApolloSearchEmailsNode;
use App\Nodes\Integrations\Apollo\ApolloSearchNewsNode;
use App\Nodes\Integrations\Apollo\ApolloSearchOrganizationsNode;
use App\Nodes\Integrations\Apollo\ApolloSearchPeopleNode;
use App\Nodes\Integrations\Apollo\ApolloSearchSequencesNode;
use App\Nodes\Integrations\Apollo\ApolloSearchTasksNode;
use App\Nodes\Integrations\Apollo\ApolloSendEmailNode;
use App\Nodes\Integrations\Apollo\ApolloSkipTaskNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateAccountNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateAccountOwnersNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateAccountStageNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateCallNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateContactNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateContactOwnersNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateContactStagesNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateCustomFieldNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateDealNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateListNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateSequenceContactStatusNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateSequenceNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateTaskNode;
use App\Nodes\Integrations\Apollo\ApolloUpsertContactNode;
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
use App\Nodes\Integrations\Datadog\DatadogAddHostTagsNode;
use App\Nodes\Integrations\Datadog\DatadogAddTeamMemberNode;
use App\Nodes\Integrations\Datadog\DatadogAddToDashboardListNode;
use App\Nodes\Integrations\Datadog\DatadogAggregateLogsNode;
use App\Nodes\Integrations\Datadog\DatadogAggregateRumEventsNode;
use App\Nodes\Integrations\Datadog\DatadogAggregateSpansNode;
use App\Nodes\Integrations\Datadog\DatadogApiRequestNode;
use App\Nodes\Integrations\Datadog\DatadogAssignErrorIssueNode;
use App\Nodes\Integrations\Datadog\DatadogAssignSignalNode;
use App\Nodes\Integrations\Datadog\DatadogCancelDowntimeNode;
use App\Nodes\Integrations\Datadog\DatadogCheckMonitorDeletableNode;
use App\Nodes\Integrations\Datadog\DatadogCheckSloDeletableNode;
use App\Nodes\Integrations\Datadog\DatadogCommentOnCaseNode;
use App\Nodes\Integrations\Datadog\DatadogCreateApiSyntheticNode;
use App\Nodes\Integrations\Datadog\DatadogCreateBrowserSyntheticNode;
use App\Nodes\Integrations\Datadog\DatadogCreateCaseNode;
use App\Nodes\Integrations\Datadog\DatadogCreateDashboardListNode;
use App\Nodes\Integrations\Datadog\DatadogCreateDashboardNode;
use App\Nodes\Integrations\Datadog\DatadogCreateDowntimeNode;
use App\Nodes\Integrations\Datadog\DatadogCreateEventNode;
use App\Nodes\Integrations\Datadog\DatadogCreateIncidentNode;
use App\Nodes\Integrations\Datadog\DatadogCreateIncidentTodoNode;
use App\Nodes\Integrations\Datadog\DatadogCreateMonitorNode;
use App\Nodes\Integrations\Datadog\DatadogCreateNotebookNode;
use App\Nodes\Integrations\Datadog\DatadogCreateSloCorrectionNode;
use App\Nodes\Integrations\Datadog\DatadogCreateSloNode;
use App\Nodes\Integrations\Datadog\DatadogCreateTeamNode;
use App\Nodes\Integrations\Datadog\DatadogCreateUserNode;
use App\Nodes\Integrations\Datadog\DatadogCreateWebhookNode;
use App\Nodes\Integrations\Datadog\DatadogDeleteDashboardListNode;
use App\Nodes\Integrations\Datadog\DatadogDeleteDashboardNode;
use App\Nodes\Integrations\Datadog\DatadogDeleteHostTagsNode;
use App\Nodes\Integrations\Datadog\DatadogDeleteIncidentNode;
use App\Nodes\Integrations\Datadog\DatadogDeleteMonitorNode;
use App\Nodes\Integrations\Datadog\DatadogDeleteNotebookNode;
use App\Nodes\Integrations\Datadog\DatadogDeleteServiceNode;
use App\Nodes\Integrations\Datadog\DatadogDeleteSloCorrectionNode;
use App\Nodes\Integrations\Datadog\DatadogDeleteSloNode;
use App\Nodes\Integrations\Datadog\DatadogDeleteSyntheticsNode;
use App\Nodes\Integrations\Datadog\DatadogDeleteTeamNode;
use App\Nodes\Integrations\Datadog\DatadogDeleteWebhookNode;
use App\Nodes\Integrations\Datadog\DatadogDisableUserNode;
use App\Nodes\Integrations\Datadog\DatadogGetCaseNode;
use App\Nodes\Integrations\Datadog\DatadogGetCurrentUserNode;
use App\Nodes\Integrations\Datadog\DatadogGetDashboardListNode;
use App\Nodes\Integrations\Datadog\DatadogGetDashboardNode;
use App\Nodes\Integrations\Datadog\DatadogGetDowntimeNode;
use App\Nodes\Integrations\Datadog\DatadogGetErrorIssueNode;
use App\Nodes\Integrations\Datadog\DatadogGetEventNode;
use App\Nodes\Integrations\Datadog\DatadogGetHostTagsNode;
use App\Nodes\Integrations\Datadog\DatadogGetHostTotalsNode;
use App\Nodes\Integrations\Datadog\DatadogGetIncidentNode;
use App\Nodes\Integrations\Datadog\DatadogGetMetricMetadataNode;
use App\Nodes\Integrations\Datadog\DatadogGetMetricTagsNode;
use App\Nodes\Integrations\Datadog\DatadogGetMonitorNode;
use App\Nodes\Integrations\Datadog\DatadogGetNotebookNode;
use App\Nodes\Integrations\Datadog\DatadogGetSecuritySignalNode;
use App\Nodes\Integrations\Datadog\DatadogGetServiceNode;
use App\Nodes\Integrations\Datadog\DatadogGetSloCorrectionNode;
use App\Nodes\Integrations\Datadog\DatadogGetSloHistoryNode;
use App\Nodes\Integrations\Datadog\DatadogGetSloNode;
use App\Nodes\Integrations\Datadog\DatadogGetSyntheticNode;
use App\Nodes\Integrations\Datadog\DatadogGetSyntheticResultNode;
use App\Nodes\Integrations\Datadog\DatadogGetSyntheticUptimeNode;
use App\Nodes\Integrations\Datadog\DatadogGetTeamNode;
use App\Nodes\Integrations\Datadog\DatadogGetTraceNode;
use App\Nodes\Integrations\Datadog\DatadogGetUserNode;
use App\Nodes\Integrations\Datadog\DatadogGetWebhookNode;
use App\Nodes\Integrations\Datadog\DatadogListActiveMetricsNode;
use App\Nodes\Integrations\Datadog\DatadogListDashboardListItemsNode;
use App\Nodes\Integrations\Datadog\DatadogListDashboardListsNode;
use App\Nodes\Integrations\Datadog\DatadogListDashboardsNode;
use App\Nodes\Integrations\Datadog\DatadogListDowntimesNode;
use App\Nodes\Integrations\Datadog\DatadogListEventsNode;
use App\Nodes\Integrations\Datadog\DatadogListHostTagsNode;
use App\Nodes\Integrations\Datadog\DatadogListHostsNode;
use App\Nodes\Integrations\Datadog\DatadogListIncidentAttachmentsNode;
use App\Nodes\Integrations\Datadog\DatadogListIncidentImpactsNode;
use App\Nodes\Integrations\Datadog\DatadogListIncidentTodosNode;
use App\Nodes\Integrations\Datadog\DatadogListIncidentsNode;
use App\Nodes\Integrations\Datadog\DatadogListMetricsNode;
use App\Nodes\Integrations\Datadog\DatadogListMonitorAlertsNode;
use App\Nodes\Integrations\Datadog\DatadogListMonitorDowntimesNode;
use App\Nodes\Integrations\Datadog\DatadogListMonitorStateChangesNode;
use App\Nodes\Integrations\Datadog\DatadogListMonitorsNode;
use App\Nodes\Integrations\Datadog\DatadogListNewIncidentsNode;
use App\Nodes\Integrations\Datadog\DatadogListNotebooksNode;
use App\Nodes\Integrations\Datadog\DatadogListServicesNode;
use App\Nodes\Integrations\Datadog\DatadogListSloCorrectionsNode;
use App\Nodes\Integrations\Datadog\DatadogListSlosNode;
use App\Nodes\Integrations\Datadog\DatadogListSyntheticLocationsNode;
use App\Nodes\Integrations\Datadog\DatadogListSyntheticResultsNode;
use App\Nodes\Integrations\Datadog\DatadogListSyntheticVariablesNode;
use App\Nodes\Integrations\Datadog\DatadogListSyntheticsNode;
use App\Nodes\Integrations\Datadog\DatadogListTeamMembersNode;
use App\Nodes\Integrations\Datadog\DatadogListTeamsNode;
use App\Nodes\Integrations\Datadog\DatadogListUsersNode;
use App\Nodes\Integrations\Datadog\DatadogMuteHostNode;
use App\Nodes\Integrations\Datadog\DatadogMuteMonitorNode;
use App\Nodes\Integrations\Datadog\DatadogQueryMetricsNode;
use App\Nodes\Integrations\Datadog\DatadogRemoveFromDashboardListNode;
use App\Nodes\Integrations\Datadog\DatadogRemoveTeamMemberNode;
use App\Nodes\Integrations\Datadog\DatadogSearchAuditEventsNode;
use App\Nodes\Integrations\Datadog\DatadogSearchCasesNode;
use App\Nodes\Integrations\Datadog\DatadogSearchErrorIssuesNode;
use App\Nodes\Integrations\Datadog\DatadogSearchEventsNode;
use App\Nodes\Integrations\Datadog\DatadogSearchIncidentsNode;
use App\Nodes\Integrations\Datadog\DatadogSearchLogsNode;
use App\Nodes\Integrations\Datadog\DatadogSearchMonitorGroupsNode;
use App\Nodes\Integrations\Datadog\DatadogSearchMonitorsNode;
use App\Nodes\Integrations\Datadog\DatadogSearchRumEventsNode;
use App\Nodes\Integrations\Datadog\DatadogSearchSecuritySignalsNode;
use App\Nodes\Integrations\Datadog\DatadogSearchSlosNode;
use App\Nodes\Integrations\Datadog\DatadogSearchSpansNode;
use App\Nodes\Integrations\Datadog\DatadogSearchSyntheticsNode;
use App\Nodes\Integrations\Datadog\DatadogSendLogsNode;
use App\Nodes\Integrations\Datadog\DatadogSetSyntheticStatusNode;
use App\Nodes\Integrations\Datadog\DatadogSubmitDistributionNode;
use App\Nodes\Integrations\Datadog\DatadogSubmitMetricNode;
use App\Nodes\Integrations\Datadog\DatadogSubmitServiceCheckNode;
use App\Nodes\Integrations\Datadog\DatadogTakeGraphSnapshotNode;
use App\Nodes\Integrations\Datadog\DatadogTriggerSyntheticsNode;
use App\Nodes\Integrations\Datadog\DatadogUnmuteHostNode;
use App\Nodes\Integrations\Datadog\DatadogUnmuteMonitorNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateApiSyntheticNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateBrowserSyntheticNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateCaseStatusNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateDashboardListNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateDashboardNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateDowntimeNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateErrorIssueStateNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateHostTagsNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateIncidentNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateMetricMetadataNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateMonitorNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateNotebookNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateSignalStateNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateSloCorrectionNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateSloNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateTeamNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateUserNode;
use App\Nodes\Integrations\Datadog\DatadogUpdateWebhookNode;
use App\Nodes\Integrations\Datadog\DatadogUpsertServiceNode;
use App\Nodes\Integrations\Datadog\DatadogValidateKeysNode;
use App\Nodes\Integrations\Datadog\DatadogValidateMonitorNode;
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
use App\Nodes\Integrations\Freshdesk\FreshdeskAddNoteNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskAddTicketTagsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskAddWatcherNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskApiRequestNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskAssignTicketToAgentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskAssignTicketToGroupNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskBulkDeleteTicketsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskBulkUpdateTicketsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCloseTicketNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateAgentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateCannedResponseFolderNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateCannedResponseNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateCompanyNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateContactNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateCustomObjectRecordNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateForumCategoryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateForumCommentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateForumNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateForumTopicNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateGroupNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateOutboundEmailNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateSatisfactionRatingNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateSolutionArticleNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateSolutionCategoryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateSolutionFolderNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateThreadMessageNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateThreadNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateTicketFieldNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateTicketNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateTimeEntryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteAgentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteAttachmentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteCompanyNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteContactNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteConversationNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteCustomObjectRecordNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteForumCategoryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteForumCommentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteForumNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteForumTopicNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteGroupNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteSkillNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteSolutionArticleNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteSolutionCategoryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteSolutionFolderNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteSummaryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteThreadMessageNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteThreadNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteTicketFieldNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteTicketNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteTimeEntryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskFindCompanyNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskFindContactNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskFollowForumTopicNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskForwardTicketNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetAccountNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetAgentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetAssociatedTicketsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetAttachmentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetBusinessHoursNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetCannedResponseFolderNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetCannedResponseNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetCompanyNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetContactNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetCurrentAgentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetCustomObjectRecordNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetCustomObjectSchemaNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetEmailConfigNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetForumCategoryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetForumNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetForumTopicNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetGroupNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetHelpdeskSettingsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetJobStatusNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetOutboundMessageNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetPrimeAssociationNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetProductNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetRoleNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetSkillNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetSolutionArticleNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetSolutionCategoryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetSolutionFolderNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetThreadMessageNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetThreadNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetTicketFieldNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetTicketNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskHardDeleteContactNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListAgentsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListAttachmentsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListBusinessHoursNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListCannedResponseFoldersNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListCannedResponsesNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListCompaniesNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListCompanyFieldsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListContactFieldsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListContactsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListConversationsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListCustomObjectRecordsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListCustomObjectSchemasNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListEmailConfigsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListForumCategoriesNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListForumsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListForumTopicsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListGroupsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListNewCompaniesNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListNewContactsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListNewNotesNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListNewTicketsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListProductsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListRolesNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListSatisfactionRatingsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListSkillsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListSlaPoliciesNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListSolutionArticlesNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListSolutionCategoriesNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListSolutionFoldersNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListSolutionSubfoldersNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListSurveysNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListTicketFieldsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListTicketFormsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListTicketRatingsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListTicketsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListTimeEntriesNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListTopicCommentsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListUpdatedContactsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListUpdatedTicketsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListWatchersNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskMakeAgentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskMergeContactsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskMergeTicketsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskRemoveTicketTagsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskRemoveWatcherNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskReplyToForwardNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskReplyToTicketNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskRestoreContactNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskRestoreTicketNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskSearchCompaniesNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskSearchContactsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskSearchSolutionsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskSearchTicketsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskSendInviteNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskSetTicketPriorityNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskSetTicketStatusNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskSetTicketTagsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskToggleTimerNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUnfollowForumTopicNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateAgentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateCannedResponseNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateCompanyNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateContactNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateConversationNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateCustomObjectRecordNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateForumCategoryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateForumCommentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateForumNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateForumTopicNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateGroupNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateSolutionArticleNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateSolutionCategoryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateSolutionFolderNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateSummaryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateThreadMessageNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateThreadNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateTicketFieldNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateTicketNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskUpdateTimeEntryNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskViewSummaryNode;
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
use App\Nodes\Integrations\HubSpot\HubSpotAddNoteNode;
use App\Nodes\Integrations\HubSpot\HubSpotAddToListNode;
use App\Nodes\Integrations\HubSpot\HubSpotAssociateNode;
use App\Nodes\Integrations\HubSpot\HubSpotCreateCompanyNode;
use App\Nodes\Integrations\HubSpot\HubSpotCreateContactNode;
use App\Nodes\Integrations\HubSpot\HubSpotCreateDealNode;
use App\Nodes\Integrations\HubSpot\HubSpotCreateTaskNode;
use App\Nodes\Integrations\HubSpot\HubSpotCreateTicketNode;
use App\Nodes\Integrations\HubSpot\HubSpotDeleteCompanyNode;
use App\Nodes\Integrations\HubSpot\HubSpotDeleteContactNode;
use App\Nodes\Integrations\HubSpot\HubSpotDeleteDealNode;
use App\Nodes\Integrations\HubSpot\HubSpotDeleteTicketNode;
use App\Nodes\Integrations\HubSpot\HubSpotGetAccountNode;
use App\Nodes\Integrations\HubSpot\HubSpotGetCompanyNode;
use App\Nodes\Integrations\HubSpot\HubSpotGetContactNode;
use App\Nodes\Integrations\HubSpot\HubSpotGetDealNode;
use App\Nodes\Integrations\HubSpot\HubSpotGetTicketNode;
use App\Nodes\Integrations\HubSpot\HubSpotListChangedCompaniesNode;
use App\Nodes\Integrations\HubSpot\HubSpotListChangedContactsNode;
use App\Nodes\Integrations\HubSpot\HubSpotListChangedDealsNode;
use App\Nodes\Integrations\HubSpot\HubSpotListChangedTicketsNode;
use App\Nodes\Integrations\HubSpot\HubSpotListNewCompaniesNode;
use App\Nodes\Integrations\HubSpot\HubSpotListNewContactsNode;
use App\Nodes\Integrations\HubSpot\HubSpotListNewDealsNode;
use App\Nodes\Integrations\HubSpot\HubSpotListNewTicketsNode;
use App\Nodes\Integrations\HubSpot\HubSpotListOwnersNode;
use App\Nodes\Integrations\HubSpot\HubSpotListPipelinesNode;
use App\Nodes\Integrations\HubSpot\HubSpotListPropertiesNode;
use App\Nodes\Integrations\HubSpot\HubSpotRemoveFromListNode;
use App\Nodes\Integrations\HubSpot\HubSpotSearchCompaniesNode;
use App\Nodes\Integrations\HubSpot\HubSpotSearchContactsNode;
use App\Nodes\Integrations\HubSpot\HubSpotSearchDealsNode;
use App\Nodes\Integrations\HubSpot\HubSpotSearchTicketsNode;
use App\Nodes\Integrations\HubSpot\HubSpotUpdateCompanyNode;
use App\Nodes\Integrations\HubSpot\HubSpotUpdateContactNode;
use App\Nodes\Integrations\HubSpot\HubSpotUpdateDealNode;
use App\Nodes\Integrations\HubSpot\HubSpotUpdateTicketNode;
use App\Nodes\Integrations\HubSpot\HubSpotUpsertContactNode;
use App\Nodes\Integrations\Intercom\IntercomApiRequestNode;
use App\Nodes\Integrations\Intercom\IntercomArchiveContactNode;
use App\Nodes\Integrations\Intercom\IntercomAssignConversationNode;
use App\Nodes\Integrations\Intercom\IntercomAttachContactCompanyNode;
use App\Nodes\Integrations\Intercom\IntercomAttachConversationContactNode;
use App\Nodes\Integrations\Intercom\IntercomBlockContactNode;
use App\Nodes\Integrations\Intercom\IntercomCloseConversationNode;
use App\Nodes\Integrations\Intercom\IntercomConvertConversationNode;
use App\Nodes\Integrations\Intercom\IntercomCreateArticleNode;
use App\Nodes\Integrations\Intercom\IntercomCreateCompanyNode;
use App\Nodes\Integrations\Intercom\IntercomCreateContactNode;
use App\Nodes\Integrations\Intercom\IntercomCreateContactNoteNode;
use App\Nodes\Integrations\Intercom\IntercomCreateConversationNode;
use App\Nodes\Integrations\Intercom\IntercomCreateDataAttributeNode;
use App\Nodes\Integrations\Intercom\IntercomCreateTagNode;
use App\Nodes\Integrations\Intercom\IntercomCreateTicketNode;
use App\Nodes\Integrations\Intercom\IntercomDeleteArticleNode;
use App\Nodes\Integrations\Intercom\IntercomDeleteCompanyNode;
use App\Nodes\Integrations\Intercom\IntercomDeleteContactNode;
use App\Nodes\Integrations\Intercom\IntercomDeleteTagNode;
use App\Nodes\Integrations\Intercom\IntercomDetachContactCompanyNode;
use App\Nodes\Integrations\Intercom\IntercomDetachConversationContactNode;
use App\Nodes\Integrations\Intercom\IntercomFindCompanyNode;
use App\Nodes\Integrations\Intercom\IntercomFindContactByExternalIdNode;
use App\Nodes\Integrations\Intercom\IntercomFindLeadNode;
use App\Nodes\Integrations\Intercom\IntercomFindOrCreateCompanyNode;
use App\Nodes\Integrations\Intercom\IntercomFindOrCreateLeadNode;
use App\Nodes\Integrations\Intercom\IntercomFindUserNode;
use App\Nodes\Integrations\Intercom\IntercomGetAdminNode;
use App\Nodes\Integrations\Intercom\IntercomGetArticleNode;
use App\Nodes\Integrations\Intercom\IntercomGetCompanyNode;
use App\Nodes\Integrations\Intercom\IntercomGetContactNode;
use App\Nodes\Integrations\Intercom\IntercomGetConversationNode;
use App\Nodes\Integrations\Intercom\IntercomGetJobStatusNode;
use App\Nodes\Integrations\Intercom\IntercomGetMeNode;
use App\Nodes\Integrations\Intercom\IntercomGetNoteNode;
use App\Nodes\Integrations\Intercom\IntercomGetSegmentNode;
use App\Nodes\Integrations\Intercom\IntercomGetTagNode;
use App\Nodes\Integrations\Intercom\IntercomGetTeamNode;
use App\Nodes\Integrations\Intercom\IntercomGetTicketNode;
use App\Nodes\Integrations\Intercom\IntercomListActivityLogsNode;
use App\Nodes\Integrations\Intercom\IntercomListAdminRepliesNode;
use App\Nodes\Integrations\Intercom\IntercomListAdminsNode;
use App\Nodes\Integrations\Intercom\IntercomListArticlesNode;
use App\Nodes\Integrations\Intercom\IntercomListClosedConversationsNode;
use App\Nodes\Integrations\Intercom\IntercomListCollectionsNode;
use App\Nodes\Integrations\Intercom\IntercomListCompaniesNode;
use App\Nodes\Integrations\Intercom\IntercomListCompanyContactsNode;
use App\Nodes\Integrations\Intercom\IntercomListCompanySegmentsNode;
use App\Nodes\Integrations\Intercom\IntercomListContactCompaniesNode;
use App\Nodes\Integrations\Intercom\IntercomListContactEventsNode;
use App\Nodes\Integrations\Intercom\IntercomListContactNotesNode;
use App\Nodes\Integrations\Intercom\IntercomListContactRepliesNode;
use App\Nodes\Integrations\Intercom\IntercomListContactSegmentsNode;
use App\Nodes\Integrations\Intercom\IntercomListContactsNode;
use App\Nodes\Integrations\Intercom\IntercomListContactSubscriptionsNode;
use App\Nodes\Integrations\Intercom\IntercomListContactTagsNode;
use App\Nodes\Integrations\Intercom\IntercomListConversationsNode;
use App\Nodes\Integrations\Intercom\IntercomListDataAttributesNode;
use App\Nodes\Integrations\Intercom\IntercomListLeadsWithEmailNode;
use App\Nodes\Integrations\Intercom\IntercomListNewCompaniesNode;
use App\Nodes\Integrations\Intercom\IntercomListNewConversationsNode;
use App\Nodes\Integrations\Intercom\IntercomListNewLeadsNode;
use App\Nodes\Integrations\Intercom\IntercomListNewTicketsNode;
use App\Nodes\Integrations\Intercom\IntercomListNewUsersNode;
use App\Nodes\Integrations\Intercom\IntercomListRatedConversationsNode;
use App\Nodes\Integrations\Intercom\IntercomListSegmentsNode;
use App\Nodes\Integrations\Intercom\IntercomListSubscriptionTypesNode;
use App\Nodes\Integrations\Intercom\IntercomListTaggedConversationsNode;
use App\Nodes\Integrations\Intercom\IntercomListTaggedLeadsNode;
use App\Nodes\Integrations\Intercom\IntercomListTaggedUsersNode;
use App\Nodes\Integrations\Intercom\IntercomListTagsNode;
use App\Nodes\Integrations\Intercom\IntercomListTeamsNode;
use App\Nodes\Integrations\Intercom\IntercomListTicketTypesNode;
use App\Nodes\Integrations\Intercom\IntercomListUnsubscribesNode;
use App\Nodes\Integrations\Intercom\IntercomListUpdatedCompaniesNode;
use App\Nodes\Integrations\Intercom\IntercomListUpdatedContactsNode;
use App\Nodes\Integrations\Intercom\IntercomListUpdatedTicketsNode;
use App\Nodes\Integrations\Intercom\IntercomMarkConversationReadNode;
use App\Nodes\Integrations\Intercom\IntercomMergeContactsNode;
use App\Nodes\Integrations\Intercom\IntercomOpenConversationNode;
use App\Nodes\Integrations\Intercom\IntercomReplyAsContactNode;
use App\Nodes\Integrations\Intercom\IntercomReplyToConversationNode;
use App\Nodes\Integrations\Intercom\IntercomReplyToTicketNode;
use App\Nodes\Integrations\Intercom\IntercomScrollCompaniesNode;
use App\Nodes\Integrations\Intercom\IntercomSearchArticlesNode;
use App\Nodes\Integrations\Intercom\IntercomSearchCompaniesNode;
use App\Nodes\Integrations\Intercom\IntercomSearchContactsNode;
use App\Nodes\Integrations\Intercom\IntercomSearchConversationsNode;
use App\Nodes\Integrations\Intercom\IntercomSearchTicketsNode;
use App\Nodes\Integrations\Intercom\IntercomSendMessageNode;
use App\Nodes\Integrations\Intercom\IntercomSetAdminAwayNode;
use App\Nodes\Integrations\Intercom\IntercomSnoozeConversationNode;
use App\Nodes\Integrations\Intercom\IntercomSubmitEventNode;
use App\Nodes\Integrations\Intercom\IntercomTagCompanyNode;
use App\Nodes\Integrations\Intercom\IntercomTagContactNode;
use App\Nodes\Integrations\Intercom\IntercomTagConversationNode;
use App\Nodes\Integrations\Intercom\IntercomTagTicketNode;
use App\Nodes\Integrations\Intercom\IntercomUnarchiveContactNode;
use App\Nodes\Integrations\Intercom\IntercomUntagContactNode;
use App\Nodes\Integrations\Intercom\IntercomUntagConversationNode;
use App\Nodes\Integrations\Intercom\IntercomUntagTicketNode;
use App\Nodes\Integrations\Intercom\IntercomUpdateArticleNode;
use App\Nodes\Integrations\Intercom\IntercomUpdateCompanyNode;
use App\Nodes\Integrations\Intercom\IntercomUpdateContactNode;
use App\Nodes\Integrations\Intercom\IntercomUpdateConversationNode;
use App\Nodes\Integrations\Intercom\IntercomUpdateDataAttributeNode;
use App\Nodes\Integrations\Intercom\IntercomUpdateTicketNode;
use App\Nodes\Integrations\Intercom\IntercomUpsertContactNode;
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
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsApiRequestNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsAddAccountUserNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsAddCompanyToAudienceNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsAddContactToAudienceNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsAddConversionToCampaignNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateAccountNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateAudienceNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateCampaignGroupNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateCampaignNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateConversionNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateCreativeNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateInlineCreativeNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateLeadFormNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsDeleteAudienceNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsDeleteCampaignGroupNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsDeleteCampaignNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsDeleteCreativeNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetAccountNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetAnalyticsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetAttributedRevenueNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetAudienceNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetCampaignGroupNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetCampaignNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetConversionNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetCreativeNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetLeadFormNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetLeadNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetStatisticsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListAccountUsersNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListAudiencesNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListCampaignConversionsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListChangedCampaignGroupsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListChangedCampaignsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListChangedCreativesNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListConversionsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListLeadFormsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListLeadsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListMyAccountsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListNewCampaignGroupsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListNewCampaignsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListNewCreativesNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListNewEventLeadsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListNewLeadFormsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListNewLeadsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListNewPageLeadsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsPreviewCreativeNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsRemoveAccountUserNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsRemoveCompanyFromAudienceNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsRemoveContactFromAudienceNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsRemoveConversionFromCampaignNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsSearchAccountsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsSearchCampaignGroupsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsSearchCampaignsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsSearchCreativesNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsSendConversionEventNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsSetCampaignStatusNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsUpdateAccountNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsUpdateAudienceNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsUpdateCampaignGroupNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsUpdateCampaignNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsUpdateConversionNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsUpdateCreativeNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsUpdateLeadFormNode;
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
use App\Nodes\Integrations\MetaAds\MetaAdsApiRequestNode;
use App\Nodes\Integrations\MetaAds\MetaAdsAddEmailToAudienceNode;
use App\Nodes\Integrations\MetaAds\MetaAdsAddPeopleToAudienceNode;
use App\Nodes\Integrations\MetaAds\MetaAdsArchiveAdNode;
use App\Nodes\Integrations\MetaAds\MetaAdsArchiveAdSetNode;
use App\Nodes\Integrations\MetaAds\MetaAdsArchiveCampaignNode;
use App\Nodes\Integrations\MetaAds\MetaAdsArchiveLeadFormNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCopyAdNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCopyAdSetNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCopyCampaignNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateAdNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateAdSetNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateAdsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateAudienceNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateCampaignNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateCatalogNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateCreativeNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateLeadFormNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateLookalikeNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreatePixelNode;
use App\Nodes\Integrations\MetaAds\MetaAdsDeleteAdNode;
use App\Nodes\Integrations\MetaAds\MetaAdsDeleteAdSetNode;
use App\Nodes\Integrations\MetaAds\MetaAdsDeleteAudienceNode;
use App\Nodes\Integrations\MetaAds\MetaAdsDeleteCampaignNode;
use App\Nodes\Integrations\MetaAds\MetaAdsDeleteCampaignsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsDeleteCreativeNode;
use App\Nodes\Integrations\MetaAds\MetaAdsDeleteImageNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetAccountNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetAdNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetAdSetNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetAudienceNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetCampaignNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetCatalogDiagnosticsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetCatalogNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetCreativeNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetDeliveryEstimateNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetInsightsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetInsightsReportNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetLeadFormNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetLeadNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetPageNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetPagePostNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetPixelNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetPixelStatsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetProductNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetReachEstimateNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListAccountsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListActivitiesNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListAdLeadsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListAdSetsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListAdsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListAudiencesNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListBusinessPagesNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListBusinessesNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListCampaignsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListCatalogsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListChangedAdSetsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListChangedAdsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListChangedCampaignsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListCreativesNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListImagesNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListLeadFormsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListLeadsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListNewAdSetsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListNewAdsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListNewCampaignsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListNewLeadsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListPagePostsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListPagesNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListPixelsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListPostCommentsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListPostReactionsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListProductFeedRulesNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListProductFeedsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListProductSetProductsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListProductSetsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListProductsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListSavedAudiencesNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListVideosNode;
use App\Nodes\Integrations\MetaAds\MetaAdsPreviewAdNode;
use App\Nodes\Integrations\MetaAds\MetaAdsPreviewCreativeNode;
use App\Nodes\Integrations\MetaAds\MetaAdsRemoveEmailFromAudienceNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSearchTargetingNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSendConversionEventNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSendFunnelEventNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSendLeadEventNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSendPurchaseEventNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSetAdSetStatusNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSetAdStatusNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSetCampaignStatusNode;
use App\Nodes\Integrations\MetaAds\MetaAdsStartInsightsReportNode;
use App\Nodes\Integrations\MetaAds\MetaAdsUpdateAdNode;
use App\Nodes\Integrations\MetaAds\MetaAdsUpdateAdSetNode;
use App\Nodes\Integrations\MetaAds\MetaAdsUpdateAudienceNode;
use App\Nodes\Integrations\MetaAds\MetaAdsUpdateCampaignNode;
use App\Nodes\Integrations\MetaAds\MetaAdsUpdateCreativeNode;
use App\Nodes\Integrations\MetaAds\MetaAdsUploadImageNode;
use App\Nodes\Integrations\Monday\MondayAddUpdateNode;
use App\Nodes\Integrations\Monday\MondayArchiveItemNode;
use App\Nodes\Integrations\Monday\MondayCreateBoardNode;
use App\Nodes\Integrations\Monday\MondayCreateGroupNode;
use App\Nodes\Integrations\Monday\MondayCreateItemNode;
use App\Nodes\Integrations\Monday\MondayCreateSubitemNode;
use App\Nodes\Integrations\Monday\MondayDeleteItemNode;
use App\Nodes\Integrations\Monday\MondayGetBoardNode;
use App\Nodes\Integrations\Monday\MondayGetItemNode;
use App\Nodes\Integrations\Monday\MondayGetMeNode;
use App\Nodes\Integrations\Monday\MondayListBoardsNode;
use App\Nodes\Integrations\Monday\MondayListItemsNode;
use App\Nodes\Integrations\Monday\MondayListNewItemsNode;
use App\Nodes\Integrations\Monday\MondayListUpdatedItemsNode;
use App\Nodes\Integrations\Monday\MondayListUpdatesNode;
use App\Nodes\Integrations\Monday\MondayListUsersNode;
use App\Nodes\Integrations\Monday\MondayListWorkspacesNode;
use App\Nodes\Integrations\Monday\MondayMoveItemNode;
use App\Nodes\Integrations\Monday\MondaySetColumnNode;
use App\Nodes\Integrations\Monday\MondayUpdateItemNode;
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
use App\Nodes\Integrations\Pipedrive\PipedriveAddNoteNode;
use App\Nodes\Integrations\Pipedrive\PipedriveCreateActivityNode;
use App\Nodes\Integrations\Pipedrive\PipedriveCreateDealNode;
use App\Nodes\Integrations\Pipedrive\PipedriveCreateLeadNode;
use App\Nodes\Integrations\Pipedrive\PipedriveCreateOrganizationNode;
use App\Nodes\Integrations\Pipedrive\PipedriveCreatePersonNode;
use App\Nodes\Integrations\Pipedrive\PipedriveDeleteActivityNode;
use App\Nodes\Integrations\Pipedrive\PipedriveDeleteDealNode;
use App\Nodes\Integrations\Pipedrive\PipedriveDeleteLeadNode;
use App\Nodes\Integrations\Pipedrive\PipedriveDeleteOrganizationNode;
use App\Nodes\Integrations\Pipedrive\PipedriveDeletePersonNode;
use App\Nodes\Integrations\Pipedrive\PipedriveGetActivityNode;
use App\Nodes\Integrations\Pipedrive\PipedriveGetDealNode;
use App\Nodes\Integrations\Pipedrive\PipedriveGetLeadNode;
use App\Nodes\Integrations\Pipedrive\PipedriveGetMeNode;
use App\Nodes\Integrations\Pipedrive\PipedriveGetOrganizationNode;
use App\Nodes\Integrations\Pipedrive\PipedriveGetPersonNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListActivitiesNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListChangedActivitiesNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListChangedDealsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListChangedOrganizationsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListChangedPeopleNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListDealsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListLeadsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListNewActivitiesNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListNewDealsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListNewOrganizationsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListNewPeopleNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListNotesNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListOrganizationsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListPeopleNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListPipelinesNode;
use App\Nodes\Integrations\Pipedrive\PipedriveListUsersNode;
use App\Nodes\Integrations\Pipedrive\PipedriveMarkDealNode;
use App\Nodes\Integrations\Pipedrive\PipedriveSearchDealsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveSearchLeadsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveSearchOrganizationsNode;
use App\Nodes\Integrations\Pipedrive\PipedriveSearchPeopleNode;
use App\Nodes\Integrations\Pipedrive\PipedriveUpdateActivityNode;
use App\Nodes\Integrations\Pipedrive\PipedriveUpdateDealNode;
use App\Nodes\Integrations\Pipedrive\PipedriveUpdateLeadNode;
use App\Nodes\Integrations\Pipedrive\PipedriveUpdateOrganizationNode;
use App\Nodes\Integrations\Pipedrive\PipedriveUpdatePersonNode;
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
use App\Nodes\Integrations\Salesforce\SalesforceCreateAccountNode;
use App\Nodes\Integrations\Salesforce\SalesforceCreateCaseNode;
use App\Nodes\Integrations\Salesforce\SalesforceCreateContactNode;
use App\Nodes\Integrations\Salesforce\SalesforceCreateLeadNode;
use App\Nodes\Integrations\Salesforce\SalesforceCreateOpportunityNode;
use App\Nodes\Integrations\Salesforce\SalesforceCreateRecordNode;
use App\Nodes\Integrations\Salesforce\SalesforceCreateTaskNode;
use App\Nodes\Integrations\Salesforce\SalesforceDeleteRecordNode;
use App\Nodes\Integrations\Salesforce\SalesforceDescribeObjectNode;
use App\Nodes\Integrations\Salesforce\SalesforceFindRecordNode;
use App\Nodes\Integrations\Salesforce\SalesforceGetOrgNode;
use App\Nodes\Integrations\Salesforce\SalesforceGetRecordNode;
use App\Nodes\Integrations\Salesforce\SalesforceListChangedAccountsNode;
use App\Nodes\Integrations\Salesforce\SalesforceListChangedCasesNode;
use App\Nodes\Integrations\Salesforce\SalesforceListChangedContactsNode;
use App\Nodes\Integrations\Salesforce\SalesforceListChangedLeadsNode;
use App\Nodes\Integrations\Salesforce\SalesforceListChangedOpportunitiesNode;
use App\Nodes\Integrations\Salesforce\SalesforceListNewAccountsNode;
use App\Nodes\Integrations\Salesforce\SalesforceListNewCasesNode;
use App\Nodes\Integrations\Salesforce\SalesforceListNewContactsNode;
use App\Nodes\Integrations\Salesforce\SalesforceListNewLeadsNode;
use App\Nodes\Integrations\Salesforce\SalesforceListNewOpportunitiesNode;
use App\Nodes\Integrations\Salesforce\SalesforceListObjectsNode;
use App\Nodes\Integrations\Salesforce\SalesforceRunQueryNode;
use App\Nodes\Integrations\Salesforce\SalesforceSearchNode;
use App\Nodes\Integrations\Salesforce\SalesforceUpdateAccountNode;
use App\Nodes\Integrations\Salesforce\SalesforceUpdateCaseNode;
use App\Nodes\Integrations\Salesforce\SalesforceUpdateContactNode;
use App\Nodes\Integrations\Salesforce\SalesforceUpdateLeadNode;
use App\Nodes\Integrations\Salesforce\SalesforceUpdateOpportunityNode;
use App\Nodes\Integrations\Salesforce\SalesforceUpdateRecordNode;
use App\Nodes\Integrations\Salesforce\SalesforceUpdateTaskNode;
use App\Nodes\Integrations\Salesforce\SalesforceUpsertRecordNode;
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
use App\Nodes\Integrations\Sentry\SentryAddIssueCommentNode;
use App\Nodes\Integrations\Sentry\SentryAddMemberNode;
use App\Nodes\Integrations\Sentry\SentryAddMemberToTeamNode;
use App\Nodes\Integrations\Sentry\SentryAddTeamToProjectNode;
use App\Nodes\Integrations\Sentry\SentryApiRequestNode;
use App\Nodes\Integrations\Sentry\SentryAssignIssueNode;
use App\Nodes\Integrations\Sentry\SentryBulkDeleteIssuesNode;
use App\Nodes\Integrations\Sentry\SentryBulkUpdateIssuesNode;
use App\Nodes\Integrations\Sentry\SentryCreateAlertNode;
use App\Nodes\Integrations\Sentry\SentryCreateClientKeyNode;
use App\Nodes\Integrations\Sentry\SentryCreateCronMonitorNode;
use App\Nodes\Integrations\Sentry\SentryCreateDashboardNode;
use App\Nodes\Integrations\Sentry\SentryCreateDeployNode;
use App\Nodes\Integrations\Sentry\SentryCreateMonitorNode;
use App\Nodes\Integrations\Sentry\SentryCreateProjectNode;
use App\Nodes\Integrations\Sentry\SentryCreateReleaseNode;
use App\Nodes\Integrations\Sentry\SentryCreateSavedQueryNode;
use App\Nodes\Integrations\Sentry\SentryCreateServiceHookNode;
use App\Nodes\Integrations\Sentry\SentryCreateTeamNode;
use App\Nodes\Integrations\Sentry\SentryDeleteAlertNode;
use App\Nodes\Integrations\Sentry\SentryDeleteClientKeyNode;
use App\Nodes\Integrations\Sentry\SentryDeleteCronMonitorNode;
use App\Nodes\Integrations\Sentry\SentryDeleteDashboardNode;
use App\Nodes\Integrations\Sentry\SentryDeleteIssueCommentNode;
use App\Nodes\Integrations\Sentry\SentryDeleteIssueNode;
use App\Nodes\Integrations\Sentry\SentryDeleteMonitorNode;
use App\Nodes\Integrations\Sentry\SentryDeleteProjectNode;
use App\Nodes\Integrations\Sentry\SentryDeleteReleaseNode;
use App\Nodes\Integrations\Sentry\SentryDeleteSavedQueryNode;
use App\Nodes\Integrations\Sentry\SentryDeleteServiceHookNode;
use App\Nodes\Integrations\Sentry\SentryDeleteTeamNode;
use App\Nodes\Integrations\Sentry\SentryGetAlertNode;
use App\Nodes\Integrations\Sentry\SentryGetClientKeyNode;
use App\Nodes\Integrations\Sentry\SentryGetCronMonitorNode;
use App\Nodes\Integrations\Sentry\SentryGetDashboardNode;
use App\Nodes\Integrations\Sentry\SentryGetIssueEventNode;
use App\Nodes\Integrations\Sentry\SentryGetIssueNode;
use App\Nodes\Integrations\Sentry\SentryGetIssueTagNode;
use App\Nodes\Integrations\Sentry\SentryGetMemberNode;
use App\Nodes\Integrations\Sentry\SentryGetMonitorNode;
use App\Nodes\Integrations\Sentry\SentryGetOrganizationNode;
use App\Nodes\Integrations\Sentry\SentryGetOrganizationStatsNode;
use App\Nodes\Integrations\Sentry\SentryGetProjectEventNode;
use App\Nodes\Integrations\Sentry\SentryGetProjectNode;
use App\Nodes\Integrations\Sentry\SentryGetProjectStatsNode;
use App\Nodes\Integrations\Sentry\SentryGetReleaseNode;
use App\Nodes\Integrations\Sentry\SentryGetSavedQueryNode;
use App\Nodes\Integrations\Sentry\SentryGetServiceHookNode;
use App\Nodes\Integrations\Sentry\SentryGetSessionStatsNode;
use App\Nodes\Integrations\Sentry\SentryGetStatsByProjectNode;
use App\Nodes\Integrations\Sentry\SentryGetTeamNode;
use App\Nodes\Integrations\Sentry\SentryGetTraceNode;
use App\Nodes\Integrations\Sentry\SentryIgnoreIssueNode;
use App\Nodes\Integrations\Sentry\SentryListAlertsNode;
use App\Nodes\Integrations\Sentry\SentryListChangedIssuesNode;
use App\Nodes\Integrations\Sentry\SentryListClientKeysNode;
use App\Nodes\Integrations\Sentry\SentryListCronCheckinsNode;
use App\Nodes\Integrations\Sentry\SentryListCronMonitorsNode;
use App\Nodes\Integrations\Sentry\SentryListDashboardsNode;
use App\Nodes\Integrations\Sentry\SentryListDeploysNode;
use App\Nodes\Integrations\Sentry\SentryListEnvironmentsNode;
use App\Nodes\Integrations\Sentry\SentryListIntegrationsNode;
use App\Nodes\Integrations\Sentry\SentryListIssueCommentsNode;
use App\Nodes\Integrations\Sentry\SentryListIssueEventsNode;
use App\Nodes\Integrations\Sentry\SentryListIssueHashesNode;
use App\Nodes\Integrations\Sentry\SentryListIssueTagValuesNode;
use App\Nodes\Integrations\Sentry\SentryListIssuesNode;
use App\Nodes\Integrations\Sentry\SentryListMembersNode;
use App\Nodes\Integrations\Sentry\SentryListMonitorsNode;
use App\Nodes\Integrations\Sentry\SentryListNewIssuesNode;
use App\Nodes\Integrations\Sentry\SentryListOrganizationsNode;
use App\Nodes\Integrations\Sentry\SentryListProjectEventsNode;
use App\Nodes\Integrations\Sentry\SentryListProjectMembersNode;
use App\Nodes\Integrations\Sentry\SentryListProjectTeamsNode;
use App\Nodes\Integrations\Sentry\SentryListProjectUsersNode;
use App\Nodes\Integrations\Sentry\SentryListProjectsNode;
use App\Nodes\Integrations\Sentry\SentryListRegressedIssuesNode;
use App\Nodes\Integrations\Sentry\SentryListReleaseCommitsNode;
use App\Nodes\Integrations\Sentry\SentryListReleaseFilesNode;
use App\Nodes\Integrations\Sentry\SentryListReleasesNode;
use App\Nodes\Integrations\Sentry\SentryListReplaysNode;
use App\Nodes\Integrations\Sentry\SentryListRepositoriesNode;
use App\Nodes\Integrations\Sentry\SentryListRepositoryCommitsNode;
use App\Nodes\Integrations\Sentry\SentryListSavedQueriesNode;
use App\Nodes\Integrations\Sentry\SentryListServiceHooksNode;
use App\Nodes\Integrations\Sentry\SentryListTagValuesNode;
use App\Nodes\Integrations\Sentry\SentryListTagsNode;
use App\Nodes\Integrations\Sentry\SentryListTeamMembersNode;
use App\Nodes\Integrations\Sentry\SentryListTeamProjectsNode;
use App\Nodes\Integrations\Sentry\SentryListTeamsNode;
use App\Nodes\Integrations\Sentry\SentryListUserFeedbackNode;
use App\Nodes\Integrations\Sentry\SentryQueryEventsNode;
use App\Nodes\Integrations\Sentry\SentryRemoveMemberFromTeamNode;
use App\Nodes\Integrations\Sentry\SentryRemoveMemberNode;
use App\Nodes\Integrations\Sentry\SentryRemoveTeamFromProjectNode;
use App\Nodes\Integrations\Sentry\SentryResolveEventIdNode;
use App\Nodes\Integrations\Sentry\SentryResolveIssueNode;
use App\Nodes\Integrations\Sentry\SentryResolveShortIdNode;
use App\Nodes\Integrations\Sentry\SentryUnresolveIssueNode;
use App\Nodes\Integrations\Sentry\SentryUpdateAlertNode;
use App\Nodes\Integrations\Sentry\SentryUpdateClientKeyNode;
use App\Nodes\Integrations\Sentry\SentryUpdateCronMonitorNode;
use App\Nodes\Integrations\Sentry\SentryUpdateDashboardNode;
use App\Nodes\Integrations\Sentry\SentryUpdateIssueCommentNode;
use App\Nodes\Integrations\Sentry\SentryUpdateIssueNode;
use App\Nodes\Integrations\Sentry\SentryUpdateMemberRoleNode;
use App\Nodes\Integrations\Sentry\SentryUpdateMonitorNode;
use App\Nodes\Integrations\Sentry\SentryUpdateOrganizationNode;
use App\Nodes\Integrations\Sentry\SentryUpdateProjectNode;
use App\Nodes\Integrations\Sentry\SentryUpdateReleaseNode;
use App\Nodes\Integrations\Sentry\SentryUpdateSavedQueryNode;
use App\Nodes\Integrations\Sentry\SentryUpdateServiceHookNode;
use App\Nodes\Integrations\Sentry\SentryUpdateTeamNode;
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
use App\Nodes\Integrations\Zendesk\ZendeskAddCommentNode;
use App\Nodes\Integrations\Zendesk\ZendeskAddTagsNode;
use App\Nodes\Integrations\Zendesk\ZendeskApiRequestNode;
use App\Nodes\Integrations\Zendesk\ZendeskApplyMacroNode;
use App\Nodes\Integrations\Zendesk\ZendeskAttachFileToTicketNode;
use App\Nodes\Integrations\Zendesk\ZendeskBatchCreateTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskBatchUpdateTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskCountOrganizationsNode;
use App\Nodes\Integrations\Zendesk\ZendeskCountTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskCountViewTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskCreateGroupNode;
use App\Nodes\Integrations\Zendesk\ZendeskCreateOrganizationNode;
use App\Nodes\Integrations\Zendesk\ZendeskCreateOrUpdateOrganizationNode;
use App\Nodes\Integrations\Zendesk\ZendeskCreateOrUpdateUserNode;
use App\Nodes\Integrations\Zendesk\ZendeskCreateSideConversationNode;
use App\Nodes\Integrations\Zendesk\ZendeskCreateTicketNode;
use App\Nodes\Integrations\Zendesk\ZendeskCreateUserNode;
use App\Nodes\Integrations\Zendesk\ZendeskDeleteGroupNode;
use App\Nodes\Integrations\Zendesk\ZendeskDeleteOrganizationNode;
use App\Nodes\Integrations\Zendesk\ZendeskDeleteSuspendedTicketNode;
use App\Nodes\Integrations\Zendesk\ZendeskDeleteTicketFieldOptionNode;
use App\Nodes\Integrations\Zendesk\ZendeskDeleteTicketNode;
use App\Nodes\Integrations\Zendesk\ZendeskDeleteTicketPermanentlyNode;
use App\Nodes\Integrations\Zendesk\ZendeskDeleteUserNode;
use App\Nodes\Integrations\Zendesk\ZendeskFindAgentNode;
use App\Nodes\Integrations\Zendesk\ZendeskFindDynamicContentNode;
use App\Nodes\Integrations\Zendesk\ZendeskFindGroupNode;
use App\Nodes\Integrations\Zendesk\ZendeskFindOrCreateOrganizationNode;
use App\Nodes\Integrations\Zendesk\ZendeskFindOrCreateUserNode;
use App\Nodes\Integrations\Zendesk\ZendeskFindUserNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetArticleNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetAttachmentNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetAuditNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetAutomationNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetDynamicContentNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetGroupNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetJobStatusNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetLatestCommentNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetMacroNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetMeNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetOrganizationNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetOrganizationRelatedNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetPostNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetSideConversationNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetTalkRecordingsNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetTicketFieldNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetTicketMetricsNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetTicketNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetTriggerNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetUserNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetUserRelatedNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetViewNode;
use App\Nodes\Integrations\Zendesk\ZendeskListActiveMacrosNode;
use App\Nodes\Integrations\Zendesk\ZendeskListArticleCommentsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListArticlesNode;
use App\Nodes\Integrations\Zendesk\ZendeskListAttachmentsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListAuditsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListAutomationsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListClosedTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListCommentsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListDeletedTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListDynamicContentNode;
use App\Nodes\Integrations\Zendesk\ZendeskListGroupsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListLocalesNode;
use App\Nodes\Integrations\Zendesk\ZendeskListMacroCategoriesNode;
use App\Nodes\Integrations\Zendesk\ZendeskListMacrosNode;
use App\Nodes\Integrations\Zendesk\ZendeskListNewOrganizationsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListNewTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListNewUsersNode;
use App\Nodes\Integrations\Zendesk\ZendeskListOrganizationsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListPendingTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListPostCommentsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListPostsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListRecentActionsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListRecentCommentsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListSideConversationEventsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListSideConversationsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListSolvedTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListSuspendedTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListTaggedTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListTaggedUsersNode;
use App\Nodes\Integrations\Zendesk\ZendeskListTagsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListTalkCallLegsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListTalkCallsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListTicketFieldOptionsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListTicketFieldsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListTicketFormsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListTriggerCategoriesNode;
use App\Nodes\Integrations\Zendesk\ZendeskListTriggersNode;
use App\Nodes\Integrations\Zendesk\ZendeskListUpdatedOrganizationsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListUpdatedTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListUpdatedUsersNode;
use App\Nodes\Integrations\Zendesk\ZendeskListUserOrganizationsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListUsersNode;
use App\Nodes\Integrations\Zendesk\ZendeskListViewsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListViewTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListViewUpdatedTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskMakeCommentPrivateNode;
use App\Nodes\Integrations\Zendesk\ZendeskMergeTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskPreviewMacroNode;
use App\Nodes\Integrations\Zendesk\ZendeskRecoverSuspendedTicketNode;
use App\Nodes\Integrations\Zendesk\ZendeskRemoveTagsNode;
use App\Nodes\Integrations\Zendesk\ZendeskReplySideConversationNode;
use App\Nodes\Integrations\Zendesk\ZendeskRestoreTicketNode;
use App\Nodes\Integrations\Zendesk\ZendeskSaveTicketFieldOptionNode;
use App\Nodes\Integrations\Zendesk\ZendeskSearchAnythingNode;
use App\Nodes\Integrations\Zendesk\ZendeskSearchArticlesNode;
use App\Nodes\Integrations\Zendesk\ZendeskSearchAutomationsNode;
use App\Nodes\Integrations\Zendesk\ZendeskSearchHelpCenterNode;
use App\Nodes\Integrations\Zendesk\ZendeskSearchMacrosNode;
use App\Nodes\Integrations\Zendesk\ZendeskSearchOrganizationsNode;
use App\Nodes\Integrations\Zendesk\ZendeskSearchPostsNode;
use App\Nodes\Integrations\Zendesk\ZendeskSearchTagsNode;
use App\Nodes\Integrations\Zendesk\ZendeskSearchTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskSearchTriggersNode;
use App\Nodes\Integrations\Zendesk\ZendeskSearchUsersNode;
use App\Nodes\Integrations\Zendesk\ZendeskSetTagsNode;
use App\Nodes\Integrations\Zendesk\ZendeskUpdateGroupNode;
use App\Nodes\Integrations\Zendesk\ZendeskUpdateOrganizationNode;
use App\Nodes\Integrations\Zendesk\ZendeskUpdateSideConversationNode;
use App\Nodes\Integrations\Zendesk\ZendeskUpdateTicketNode;
use App\Nodes\Integrations\Zendesk\ZendeskUpdateUserNode;
use App\Nodes\Integrations\Zendesk\ZendeskUploadAttachmentNode;
use App\Nodes\Integrations\Zoho\ZohoAddNoteNode;
use App\Nodes\Integrations\Zoho\ZohoCreateAccountNode;
use App\Nodes\Integrations\Zoho\ZohoCreateContactNode;
use App\Nodes\Integrations\Zoho\ZohoCreateDealNode;
use App\Nodes\Integrations\Zoho\ZohoCreateLeadNode;
use App\Nodes\Integrations\Zoho\ZohoCreateRecordNode;
use App\Nodes\Integrations\Zoho\ZohoCreateTaskNode;
use App\Nodes\Integrations\Zoho\ZohoDeleteAccountNode;
use App\Nodes\Integrations\Zoho\ZohoDeleteContactNode;
use App\Nodes\Integrations\Zoho\ZohoDeleteDealNode;
use App\Nodes\Integrations\Zoho\ZohoDeleteLeadNode;
use App\Nodes\Integrations\Zoho\ZohoDeleteTaskNode;
use App\Nodes\Integrations\Zoho\ZohoGetAccountNode;
use App\Nodes\Integrations\Zoho\ZohoGetContactNode;
use App\Nodes\Integrations\Zoho\ZohoGetDealNode;
use App\Nodes\Integrations\Zoho\ZohoGetLeadNode;
use App\Nodes\Integrations\Zoho\ZohoGetMeNode;
use App\Nodes\Integrations\Zoho\ZohoGetTaskNode;
use App\Nodes\Integrations\Zoho\ZohoListChangedAccountsNode;
use App\Nodes\Integrations\Zoho\ZohoListChangedContactsNode;
use App\Nodes\Integrations\Zoho\ZohoListChangedDealsNode;
use App\Nodes\Integrations\Zoho\ZohoListChangedLeadsNode;
use App\Nodes\Integrations\Zoho\ZohoListChangedTasksNode;
use App\Nodes\Integrations\Zoho\ZohoListFieldsNode;
use App\Nodes\Integrations\Zoho\ZohoListModulesNode;
use App\Nodes\Integrations\Zoho\ZohoListNewAccountsNode;
use App\Nodes\Integrations\Zoho\ZohoListNewContactsNode;
use App\Nodes\Integrations\Zoho\ZohoListNewDealsNode;
use App\Nodes\Integrations\Zoho\ZohoListNewLeadsNode;
use App\Nodes\Integrations\Zoho\ZohoListNewTasksNode;
use App\Nodes\Integrations\Zoho\ZohoListNotesNode;
use App\Nodes\Integrations\Zoho\ZohoListUsersNode;
use App\Nodes\Integrations\Zoho\ZohoRunCoqlNode;
use App\Nodes\Integrations\Zoho\ZohoSearchAccountsNode;
use App\Nodes\Integrations\Zoho\ZohoSearchContactsNode;
use App\Nodes\Integrations\Zoho\ZohoSearchDealsNode;
use App\Nodes\Integrations\Zoho\ZohoSearchLeadsNode;
use App\Nodes\Integrations\Zoho\ZohoSearchTasksNode;
use App\Nodes\Integrations\Zoho\ZohoUpdateAccountNode;
use App\Nodes\Integrations\Zoho\ZohoUpdateContactNode;
use App\Nodes\Integrations\Zoho\ZohoUpdateDealNode;
use App\Nodes\Integrations\Zoho\ZohoUpdateLeadNode;
use App\Nodes\Integrations\Zoho\ZohoUpdateTaskNode;
use App\Nodes\Integrations\Zoho\ZohoUpsertRecordNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksCreateBillNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksCreateCustomerNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksCreateEstimateNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksCreateInvoiceNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksCreateItemNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksCreateVendorNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksDeleteInvoiceNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksEmailEstimateNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksEmailInvoiceNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksGetBillNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksGetContactNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksGetInvoiceNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksGetOrganizationNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksListBillsNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksListContactsNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksListEstimatesNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksListInvoicesNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksListItemsNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksListNewCustomersNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksListNewInvoicesNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksListNewPaymentsNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksListPaymentsNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksMarkEstimateNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksMarkInvoiceNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksRecordPaymentNode;
use App\Nodes\Integrations\ZohoBooks\ZohoBooksUpdateContactNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsAddSubscriberNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsListCampaignsNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsListListsNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsListNewSubscribersNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsListSubscribersNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsListUnsubscribesNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsSendCampaignNode;
use App\Nodes\Integrations\ZohoCampaigns\ZohoCampaignsUnsubscribeNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlApiRequestNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlCancelAgentNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlCancelBatchScrapeNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlCancelCrawlNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlCreateMonitorNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlCreateSessionNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlDeleteMonitorNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlDeleteSessionNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlExecuteInSessionNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlFindRelatedPapersNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetAgentStatusNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetAgentTraceNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetBatchScrapeErrorsNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetBatchScrapeStatusNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetCrawlErrorsNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetCrawlStatusNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetCreditHistoryNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetCreditUsageNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetExtractStatusNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetMonitorCheckNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetMonitorNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetPaperNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetQueueStatusNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetScrapeNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetTokenHistoryNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetTokenUsageNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlInteractNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlListActiveCrawlsNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlListActivityNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlListAgentRunsNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlListFinishedBatchScrapesNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlListFinishedCrawlsNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlListFinishedExtractsNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlListMonitorChangesNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlListMonitorChecksNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlListMonitorsNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlListParseFormatsNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlListSessionsNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlMapNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlParseFileNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlPreviewCrawlParamsNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlRunDataToolNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlRunMonitorNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlScrapeNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlSearchDeveloperNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlSearchGovernmentNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlSearchNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlSearchPapersNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlSendFeedbackNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlSendSearchFeedbackNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlStartAgentNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlStartBatchScrapeNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlStartCrawlNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlStartExtractNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlStopInteractNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlUpdateMonitorNode;
use App\Nodes\Integrations\Apify\ApifyAbortBuildNode;
use App\Nodes\Integrations\Apify\ApifyAbortRunNode;
use App\Nodes\Integrations\Apify\ApifyAddDatasetItemsNode;
use App\Nodes\Integrations\Apify\ApifyApiRequestNode;
use App\Nodes\Integrations\Apify\ApifyBuildActorNode;
use App\Nodes\Integrations\Apify\ApifyCreateActorNode;
use App\Nodes\Integrations\Apify\ApifyCreateDatasetNode;
use App\Nodes\Integrations\Apify\ApifyCreateScheduleNode;
use App\Nodes\Integrations\Apify\ApifyCreateStoreNode;
use App\Nodes\Integrations\Apify\ApifyCreateTaskNode;
use App\Nodes\Integrations\Apify\ApifyCreateWebhookNode;
use App\Nodes\Integrations\Apify\ApifyDeleteActorNode;
use App\Nodes\Integrations\Apify\ApifyDeleteBuildNode;
use App\Nodes\Integrations\Apify\ApifyDeleteDatasetNode;
use App\Nodes\Integrations\Apify\ApifyDeleteRunNode;
use App\Nodes\Integrations\Apify\ApifyDeleteScheduleNode;
use App\Nodes\Integrations\Apify\ApifyDeleteStoreNode;
use App\Nodes\Integrations\Apify\ApifyDeleteStoreRecordNode;
use App\Nodes\Integrations\Apify\ApifyDeleteTaskNode;
use App\Nodes\Integrations\Apify\ApifyDeleteWebhookNode;
use App\Nodes\Integrations\Apify\ApifyGetAccountNode;
use App\Nodes\Integrations\Apify\ApifyGetActorNode;
use App\Nodes\Integrations\Apify\ApifyGetActorVersionNode;
use App\Nodes\Integrations\Apify\ApifyGetBuildLogNode;
use App\Nodes\Integrations\Apify\ApifyGetBuildNode;
use App\Nodes\Integrations\Apify\ApifyGetDatasetItemsNode;
use App\Nodes\Integrations\Apify\ApifyGetDatasetNode;
use App\Nodes\Integrations\Apify\ApifyGetDatasetSchemaNode;
use App\Nodes\Integrations\Apify\ApifyGetDatasetStatisticsNode;
use App\Nodes\Integrations\Apify\ApifyGetLastActorRunNode;
use App\Nodes\Integrations\Apify\ApifyGetLastTaskRunNode;
use App\Nodes\Integrations\Apify\ApifyGetLimitsNode;
use App\Nodes\Integrations\Apify\ApifyGetMonthlyUsageNode;
use App\Nodes\Integrations\Apify\ApifyGetRunLogNode;
use App\Nodes\Integrations\Apify\ApifyGetRunNode;
use App\Nodes\Integrations\Apify\ApifyGetRunResultsNode;
use App\Nodes\Integrations\Apify\ApifyGetScheduleLogNode;
use App\Nodes\Integrations\Apify\ApifyGetScheduleNode;
use App\Nodes\Integrations\Apify\ApifyGetStoreNode;
use App\Nodes\Integrations\Apify\ApifyGetStoreRecordNode;
use App\Nodes\Integrations\Apify\ApifyGetTaskInputNode;
use App\Nodes\Integrations\Apify\ApifyGetTaskNode;
use App\Nodes\Integrations\Apify\ApifyGetWebhookDispatchNode;
use App\Nodes\Integrations\Apify\ApifyGetWebhookNode;
use App\Nodes\Integrations\Apify\ApifyInvokeScheduleNode;
use App\Nodes\Integrations\Apify\ApifyListActorBuildsNode;
use App\Nodes\Integrations\Apify\ApifyListActorRunsNode;
use App\Nodes\Integrations\Apify\ApifyListActorsNode;
use App\Nodes\Integrations\Apify\ApifyListBuildsNode;
use App\Nodes\Integrations\Apify\ApifyListDatasetsNode;
use App\Nodes\Integrations\Apify\ApifyListRunsNode;
use App\Nodes\Integrations\Apify\ApifyListSchedulesNode;
use App\Nodes\Integrations\Apify\ApifyListStoreKeysNode;
use App\Nodes\Integrations\Apify\ApifyListStoresNode;
use App\Nodes\Integrations\Apify\ApifyListTaskRunsNode;
use App\Nodes\Integrations\Apify\ApifyListTasksNode;
use App\Nodes\Integrations\Apify\ApifyListWebhookDispatchesNode;
use App\Nodes\Integrations\Apify\ApifyListWebhooksNode;
use App\Nodes\Integrations\Apify\ApifyPublishTaskNode;
use App\Nodes\Integrations\Apify\ApifyRebootRunNode;
use App\Nodes\Integrations\Apify\ApifyResurrectRunNode;
use App\Nodes\Integrations\Apify\ApifyRunActorAndGetResultsNode;
use App\Nodes\Integrations\Apify\ApifyRunActorNode;
use App\Nodes\Integrations\Apify\ApifyRunTaskAndGetResultsNode;
use App\Nodes\Integrations\Apify\ApifyRunTaskNode;
use App\Nodes\Integrations\Apify\ApifySearchStoreNode;
use App\Nodes\Integrations\Apify\ApifySetStoreRecordNode;
use App\Nodes\Integrations\Apify\ApifyTestWebhookNode;
use App\Nodes\Integrations\Apify\ApifyUnpublishTaskNode;
use App\Nodes\Integrations\Apify\ApifyUpdateActorNode;
use App\Nodes\Integrations\Apify\ApifyUpdateActorVersionNode;
use App\Nodes\Integrations\Apify\ApifyUpdateDatasetNode;
use App\Nodes\Integrations\Apify\ApifyUpdateLimitsNode;
use App\Nodes\Integrations\Apify\ApifyUpdateScheduleNode;
use App\Nodes\Integrations\Apify\ApifyUpdateStoreNode;
use App\Nodes\Integrations\Apify\ApifyUpdateTaskInputNode;
use App\Nodes\Integrations\Apify\ApifyUpdateTaskNode;
use App\Nodes\Integrations\Apify\ApifyUpdateWebhookNode;
use App\Nodes\Integrations\Apify\ApifyValidateActorInputNode;
use App\Nodes\Integrations\Apify\ApifyWebFetchNode;
use App\Nodes\Integrations\Apify\ApifyWebSearchNode;
use App\Nodes\Integrations\Exa\ExaAnswerNode;
use App\Nodes\Integrations\Exa\ExaApiRequestNode;
use App\Nodes\Integrations\Exa\ExaBatchMonitorsNode;
use App\Nodes\Integrations\Exa\ExaCancelAgentRunNode;
use App\Nodes\Integrations\Exa\ExaCancelBatchNode;
use App\Nodes\Integrations\Exa\ExaCancelEnrichmentNode;
use App\Nodes\Integrations\Exa\ExaCancelWebsetNode;
use App\Nodes\Integrations\Exa\ExaCancelWebsetSearchNode;
use App\Nodes\Integrations\Exa\ExaCreateAgentRunNode;
use App\Nodes\Integrations\Exa\ExaCreateBatchNode;
use App\Nodes\Integrations\Exa\ExaCreateEnrichmentNode;
use App\Nodes\Integrations\Exa\ExaCreateMonitorNode;
use App\Nodes\Integrations\Exa\ExaCreateWebhookNode;
use App\Nodes\Integrations\Exa\ExaCreateWebsetMonitorNode;
use App\Nodes\Integrations\Exa\ExaCreateWebsetNode;
use App\Nodes\Integrations\Exa\ExaCreateWebsetSearchNode;
use App\Nodes\Integrations\Exa\ExaDeleteAgentRunNode;
use App\Nodes\Integrations\Exa\ExaDeleteBatchNode;
use App\Nodes\Integrations\Exa\ExaDeleteEnrichmentNode;
use App\Nodes\Integrations\Exa\ExaDeleteMonitorNode;
use App\Nodes\Integrations\Exa\ExaDeleteWebhookNode;
use App\Nodes\Integrations\Exa\ExaDeleteWebsetItemNode;
use App\Nodes\Integrations\Exa\ExaDeleteWebsetMonitorNode;
use App\Nodes\Integrations\Exa\ExaDeleteWebsetNode;
use App\Nodes\Integrations\Exa\ExaFindSimilarNode;
use App\Nodes\Integrations\Exa\ExaGetAgentRunNode;
use App\Nodes\Integrations\Exa\ExaGetBatchNode;
use App\Nodes\Integrations\Exa\ExaGetContentsNode;
use App\Nodes\Integrations\Exa\ExaGetEnrichmentNode;
use App\Nodes\Integrations\Exa\ExaGetEventNode;
use App\Nodes\Integrations\Exa\ExaGetMonitorNode;
use App\Nodes\Integrations\Exa\ExaGetMonitorRunNode;
use App\Nodes\Integrations\Exa\ExaGetTeamNode;
use App\Nodes\Integrations\Exa\ExaGetWebsetItemNode;
use App\Nodes\Integrations\Exa\ExaGetWebsetMonitorNode;
use App\Nodes\Integrations\Exa\ExaGetWebsetNode;
use App\Nodes\Integrations\Exa\ExaGetWebsetSearchNode;
use App\Nodes\Integrations\Exa\ExaListAgentRunEventsNode;
use App\Nodes\Integrations\Exa\ExaListAgentRunsNode;
use App\Nodes\Integrations\Exa\ExaListBatchesNode;
use App\Nodes\Integrations\Exa\ExaListEventsNode;
use App\Nodes\Integrations\Exa\ExaListMonitorRunsNode;
use App\Nodes\Integrations\Exa\ExaListMonitorsNode;
use App\Nodes\Integrations\Exa\ExaListWebhooksNode;
use App\Nodes\Integrations\Exa\ExaListWebsetItemsNode;
use App\Nodes\Integrations\Exa\ExaListWebsetMonitorsNode;
use App\Nodes\Integrations\Exa\ExaListWebsetsNode;
use App\Nodes\Integrations\Exa\ExaPreviewWebsetNode;
use App\Nodes\Integrations\Exa\ExaSearchNode;
use App\Nodes\Integrations\Exa\ExaStopAgentRunNode;
use App\Nodes\Integrations\Exa\ExaTriggerMonitorNode;
use App\Nodes\Integrations\Exa\ExaUpdateEnrichmentNode;
use App\Nodes\Integrations\Exa\ExaUpdateMonitorNode;
use App\Nodes\Integrations\Exa\ExaUpdateWebsetMonitorNode;
use App\Nodes\Integrations\Exa\ExaUpdateWebsetNode;
use App\Nodes\Integrations\Perplexity\PerplexityApiRequestNode;
use App\Nodes\Integrations\Perplexity\PerplexityAskNode;
use App\Nodes\Integrations\Perplexity\PerplexityCancelAgentResponseNode;
use App\Nodes\Integrations\Perplexity\PerplexityChatCompletionNode;
use App\Nodes\Integrations\Perplexity\PerplexityCreateAgentResponseNode;
use App\Nodes\Integrations\Perplexity\PerplexityCreateContextualizedEmbeddingsNode;
use App\Nodes\Integrations\Perplexity\PerplexityCreateEmbeddingsNode;
use App\Nodes\Integrations\Perplexity\PerplexityGetAgentResponseNode;
use App\Nodes\Integrations\Perplexity\PerplexityListAgentFilesNode;
use App\Nodes\Integrations\Perplexity\PerplexityListModelsNode;
use App\Nodes\Integrations\Perplexity\PerplexityListOrchestratorModelsNode;
use App\Nodes\Integrations\Perplexity\PerplexityOrchestratorChatNode;
use App\Nodes\Integrations\Perplexity\PerplexityReasonNode;
use App\Nodes\Integrations\Perplexity\PerplexitySearchNode;
use App\Nodes\Integrations\Perplexity\PerplexityStartAgentResponseNode;
use App\Nodes\Integrations\Perplexity\PerplexityStartResearchNode;
use App\Nodes\Integrations\Tavily\TavilyApiRequestNode;
use App\Nodes\Integrations\Tavily\TavilyCrawlNode;
use App\Nodes\Integrations\Tavily\TavilyExtractNode;
use App\Nodes\Integrations\Tavily\TavilyGetLogsNode;
use App\Nodes\Integrations\Tavily\TavilyGetResearchNode;
use App\Nodes\Integrations\Tavily\TavilyGetUsageNode;
use App\Nodes\Integrations\Tavily\TavilyMapNode;
use App\Nodes\Integrations\Tavily\TavilySearchNode;
use App\Nodes\Integrations\Tavily\TavilySendFeedbackNode;
use App\Nodes\Integrations\Tavily\TavilyStartResearchNode;
use App\Nodes\Integrations\ZohoCliq\ZohoCliqListChannelsNode;
use App\Nodes\Integrations\ZohoCliq\ZohoCliqListMessagesNode;
use App\Nodes\Integrations\ZohoCliq\ZohoCliqListUsersNode;
use App\Nodes\Integrations\ZohoCliq\ZohoCliqSendChannelMessageNode;
use App\Nodes\Integrations\ZohoCliq\ZohoCliqSendChatMessageNode;
use App\Nodes\Integrations\ZohoCliq\ZohoCliqSendDirectMessageNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskAddCommentNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskCloseTicketNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskCreateContactNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskCreateTicketNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskDeleteTicketNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskFindContactNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskGetMeNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskGetTicketNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskListAgentsNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskListChangedTicketsNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskListContactsNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskListCustomerRepliesNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskListDepartmentsNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskListNewTicketsNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskListThreadsNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskListTicketsNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskSearchTicketsNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskSendReplyNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskUpdateContactNode;
use App\Nodes\Integrations\ZohoDesk\ZohoDeskUpdateTicketNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailGetAccountNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailGetMessageNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailListFoldersNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailListMessagesNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailListNewMessagesNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailMarkReadNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailMoveMessageNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailSearchMessagesNode;
use App\Nodes\Integrations\ZohoMail\ZohoMailSendEmailNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsAddTaskCommentNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsCreateBugNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsCreateMilestoneNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsCreateProjectNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsCreateTaskNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsGetProjectNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsGetTaskNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsListBugsNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsListChangedTasksNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsListMilestonesNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsListNewBugsNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsListNewTasksNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsListProjectsNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsListTasksNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsListUsersNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsLogTimeNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsUpdateTaskNode;
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
            'monday_add_update' => MondayAddUpdateNode::class,
            'monday_archive_item' => MondayArchiveItemNode::class,
            'monday_create_board' => MondayCreateBoardNode::class,
            'monday_create_group' => MondayCreateGroupNode::class,
            'monday_create_item' => MondayCreateItemNode::class,
            'monday_create_subitem' => MondayCreateSubitemNode::class,
            'monday_delete_item' => MondayDeleteItemNode::class,
            'monday_get_board' => MondayGetBoardNode::class,
            'monday_get_item' => MondayGetItemNode::class,
            'monday_get_me' => MondayGetMeNode::class,
            'monday_list_boards' => MondayListBoardsNode::class,
            'monday_list_items' => MondayListItemsNode::class,
            'monday_list_new_items' => MondayListNewItemsNode::class,
            'monday_list_updated_items' => MondayListUpdatedItemsNode::class,
            'monday_list_updates' => MondayListUpdatesNode::class,
            'monday_list_users' => MondayListUsersNode::class,
            'monday_list_workspaces' => MondayListWorkspacesNode::class,
            'monday_move_item' => MondayMoveItemNode::class,
            'monday_set_column' => MondaySetColumnNode::class,
            'monday_update_item' => MondayUpdateItemNode::class,
            'hubspot_add_note' => HubSpotAddNoteNode::class,
            'hubspot_add_contacts_to_list' => HubSpotAddToListNode::class,
            'hubspot_associate' => HubSpotAssociateNode::class,
            'hubspot_create_company' => HubSpotCreateCompanyNode::class,
            'hubspot_create_contact' => HubSpotCreateContactNode::class,
            'hubspot_create_deal' => HubSpotCreateDealNode::class,
            'hubspot_create_task' => HubSpotCreateTaskNode::class,
            'hubspot_create_ticket' => HubSpotCreateTicketNode::class,
            'hubspot_delete_company' => HubSpotDeleteCompanyNode::class,
            'hubspot_delete_contact' => HubSpotDeleteContactNode::class,
            'hubspot_delete_deal' => HubSpotDeleteDealNode::class,
            'hubspot_delete_ticket' => HubSpotDeleteTicketNode::class,
            'hubspot_get_account' => HubSpotGetAccountNode::class,
            'hubspot_get_company' => HubSpotGetCompanyNode::class,
            'hubspot_get_contact' => HubSpotGetContactNode::class,
            'hubspot_get_deal' => HubSpotGetDealNode::class,
            'hubspot_get_ticket' => HubSpotGetTicketNode::class,
            'hubspot_list_changed_companies' => HubSpotListChangedCompaniesNode::class,
            'hubspot_list_changed_contacts' => HubSpotListChangedContactsNode::class,
            'hubspot_list_changed_deals' => HubSpotListChangedDealsNode::class,
            'hubspot_list_changed_tickets' => HubSpotListChangedTicketsNode::class,
            'hubspot_list_new_companies' => HubSpotListNewCompaniesNode::class,
            'hubspot_list_new_contacts' => HubSpotListNewContactsNode::class,
            'hubspot_list_new_deals' => HubSpotListNewDealsNode::class,
            'hubspot_list_new_tickets' => HubSpotListNewTicketsNode::class,
            'hubspot_list_owners' => HubSpotListOwnersNode::class,
            'hubspot_list_pipelines' => HubSpotListPipelinesNode::class,
            'hubspot_list_properties' => HubSpotListPropertiesNode::class,
            'hubspot_remove_contacts_from_list' => HubSpotRemoveFromListNode::class,
            'hubspot_search_companies' => HubSpotSearchCompaniesNode::class,
            'hubspot_search_contacts' => HubSpotSearchContactsNode::class,
            'hubspot_search_deals' => HubSpotSearchDealsNode::class,
            'hubspot_search_tickets' => HubSpotSearchTicketsNode::class,
            'hubspot_update_company' => HubSpotUpdateCompanyNode::class,
            'hubspot_update_contact' => HubSpotUpdateContactNode::class,
            'hubspot_update_deal' => HubSpotUpdateDealNode::class,
            'hubspot_update_ticket' => HubSpotUpdateTicketNode::class,
            'hubspot_upsert_contact' => HubSpotUpsertContactNode::class,
            'pipedrive_add_note' => PipedriveAddNoteNode::class,
            'pipedrive_create_activity' => PipedriveCreateActivityNode::class,
            'pipedrive_create_deal' => PipedriveCreateDealNode::class,
            'pipedrive_create_lead' => PipedriveCreateLeadNode::class,
            'pipedrive_create_organization' => PipedriveCreateOrganizationNode::class,
            'pipedrive_create_person' => PipedriveCreatePersonNode::class,
            'pipedrive_delete_activity' => PipedriveDeleteActivityNode::class,
            'pipedrive_delete_deal' => PipedriveDeleteDealNode::class,
            'pipedrive_delete_lead' => PipedriveDeleteLeadNode::class,
            'pipedrive_delete_organization' => PipedriveDeleteOrganizationNode::class,
            'pipedrive_delete_person' => PipedriveDeletePersonNode::class,
            'pipedrive_get_activity' => PipedriveGetActivityNode::class,
            'pipedrive_get_deal' => PipedriveGetDealNode::class,
            'pipedrive_get_lead' => PipedriveGetLeadNode::class,
            'pipedrive_get_me' => PipedriveGetMeNode::class,
            'pipedrive_get_organization' => PipedriveGetOrganizationNode::class,
            'pipedrive_get_person' => PipedriveGetPersonNode::class,
            'pipedrive_list_activities' => PipedriveListActivitiesNode::class,
            'pipedrive_list_changed_activities' => PipedriveListChangedActivitiesNode::class,
            'pipedrive_list_changed_deals' => PipedriveListChangedDealsNode::class,
            'pipedrive_list_changed_organizations' => PipedriveListChangedOrganizationsNode::class,
            'pipedrive_list_changed_persons' => PipedriveListChangedPeopleNode::class,
            'pipedrive_list_deals' => PipedriveListDealsNode::class,
            'pipedrive_list_leads' => PipedriveListLeadsNode::class,
            'pipedrive_list_new_activities' => PipedriveListNewActivitiesNode::class,
            'pipedrive_list_new_deals' => PipedriveListNewDealsNode::class,
            'pipedrive_list_new_organizations' => PipedriveListNewOrganizationsNode::class,
            'pipedrive_list_new_persons' => PipedriveListNewPeopleNode::class,
            'pipedrive_list_notes' => PipedriveListNotesNode::class,
            'pipedrive_list_organizations' => PipedriveListOrganizationsNode::class,
            'pipedrive_list_persons' => PipedriveListPeopleNode::class,
            'pipedrive_list_pipelines' => PipedriveListPipelinesNode::class,
            'pipedrive_list_users' => PipedriveListUsersNode::class,
            'pipedrive_mark_deal' => PipedriveMarkDealNode::class,
            'pipedrive_search_deals' => PipedriveSearchDealsNode::class,
            'pipedrive_search_leads' => PipedriveSearchLeadsNode::class,
            'pipedrive_search_organizations' => PipedriveSearchOrganizationsNode::class,
            'pipedrive_search_persons' => PipedriveSearchPeopleNode::class,
            'pipedrive_update_activity' => PipedriveUpdateActivityNode::class,
            'pipedrive_update_deal' => PipedriveUpdateDealNode::class,
            'pipedrive_update_lead' => PipedriveUpdateLeadNode::class,
            'pipedrive_update_organization' => PipedriveUpdateOrganizationNode::class,
            'pipedrive_update_person' => PipedriveUpdatePersonNode::class,
            'salesforce_create_account' => SalesforceCreateAccountNode::class,
            'salesforce_create_case' => SalesforceCreateCaseNode::class,
            'salesforce_create_contact' => SalesforceCreateContactNode::class,
            'salesforce_create_lead' => SalesforceCreateLeadNode::class,
            'salesforce_create_opportunity' => SalesforceCreateOpportunityNode::class,
            'salesforce_create_record' => SalesforceCreateRecordNode::class,
            'salesforce_create_task' => SalesforceCreateTaskNode::class,
            'salesforce_delete_record' => SalesforceDeleteRecordNode::class,
            'salesforce_describe_object' => SalesforceDescribeObjectNode::class,
            'salesforce_find_record' => SalesforceFindRecordNode::class,
            'salesforce_get_org' => SalesforceGetOrgNode::class,
            'salesforce_get_record' => SalesforceGetRecordNode::class,
            'salesforce_list_changed_accounts' => SalesforceListChangedAccountsNode::class,
            'salesforce_list_changed_cases' => SalesforceListChangedCasesNode::class,
            'salesforce_list_changed_contacts' => SalesforceListChangedContactsNode::class,
            'salesforce_list_changed_leads' => SalesforceListChangedLeadsNode::class,
            'salesforce_list_changed_opportunities' => SalesforceListChangedOpportunitiesNode::class,
            'salesforce_list_new_accounts' => SalesforceListNewAccountsNode::class,
            'salesforce_list_new_cases' => SalesforceListNewCasesNode::class,
            'salesforce_list_new_contacts' => SalesforceListNewContactsNode::class,
            'salesforce_list_new_leads' => SalesforceListNewLeadsNode::class,
            'salesforce_list_new_opportunities' => SalesforceListNewOpportunitiesNode::class,
            'salesforce_list_objects' => SalesforceListObjectsNode::class,
            'salesforce_run_query' => SalesforceRunQueryNode::class,
            'salesforce_search' => SalesforceSearchNode::class,
            'salesforce_update_account' => SalesforceUpdateAccountNode::class,
            'salesforce_update_case' => SalesforceUpdateCaseNode::class,
            'salesforce_update_contact' => SalesforceUpdateContactNode::class,
            'salesforce_update_lead' => SalesforceUpdateLeadNode::class,
            'salesforce_update_opportunity' => SalesforceUpdateOpportunityNode::class,
            'salesforce_update_record' => SalesforceUpdateRecordNode::class,
            'salesforce_update_task' => SalesforceUpdateTaskNode::class,
            'salesforce_upsert_record' => SalesforceUpsertRecordNode::class,
            'zoho_add_note' => ZohoAddNoteNode::class,
            'zoho_create_account' => ZohoCreateAccountNode::class,
            'zoho_create_contact' => ZohoCreateContactNode::class,
            'zoho_create_deal' => ZohoCreateDealNode::class,
            'zoho_create_lead' => ZohoCreateLeadNode::class,
            'zoho_create_record' => ZohoCreateRecordNode::class,
            'zoho_create_task' => ZohoCreateTaskNode::class,
            'zoho_delete_account' => ZohoDeleteAccountNode::class,
            'zoho_delete_contact' => ZohoDeleteContactNode::class,
            'zoho_delete_deal' => ZohoDeleteDealNode::class,
            'zoho_delete_lead' => ZohoDeleteLeadNode::class,
            'zoho_delete_task' => ZohoDeleteTaskNode::class,
            'zoho_get_account' => ZohoGetAccountNode::class,
            'zoho_get_contact' => ZohoGetContactNode::class,
            'zoho_get_deal' => ZohoGetDealNode::class,
            'zoho_get_lead' => ZohoGetLeadNode::class,
            'zoho_get_me' => ZohoGetMeNode::class,
            'zoho_get_task' => ZohoGetTaskNode::class,
            'zoho_list_changed_accounts' => ZohoListChangedAccountsNode::class,
            'zoho_list_changed_contacts' => ZohoListChangedContactsNode::class,
            'zoho_list_changed_deals' => ZohoListChangedDealsNode::class,
            'zoho_list_changed_leads' => ZohoListChangedLeadsNode::class,
            'zoho_list_changed_tasks' => ZohoListChangedTasksNode::class,
            'zoho_list_fields' => ZohoListFieldsNode::class,
            'zoho_list_modules' => ZohoListModulesNode::class,
            'zoho_list_new_accounts' => ZohoListNewAccountsNode::class,
            'zoho_list_new_contacts' => ZohoListNewContactsNode::class,
            'zoho_list_new_deals' => ZohoListNewDealsNode::class,
            'zoho_list_new_leads' => ZohoListNewLeadsNode::class,
            'zoho_list_new_tasks' => ZohoListNewTasksNode::class,
            'zoho_list_notes' => ZohoListNotesNode::class,
            'zoho_list_users' => ZohoListUsersNode::class,
            'zoho_run_coql' => ZohoRunCoqlNode::class,
            'zoho_search_accounts' => ZohoSearchAccountsNode::class,
            'zoho_search_contacts' => ZohoSearchContactsNode::class,
            'zoho_search_deals' => ZohoSearchDealsNode::class,
            'zoho_search_leads' => ZohoSearchLeadsNode::class,
            'zoho_search_tasks' => ZohoSearchTasksNode::class,
            'zoho_update_account' => ZohoUpdateAccountNode::class,
            'zoho_update_contact' => ZohoUpdateContactNode::class,
            'zoho_update_deal' => ZohoUpdateDealNode::class,
            'zoho_update_lead' => ZohoUpdateLeadNode::class,
            'zoho_update_task' => ZohoUpdateTaskNode::class,
            'zoho_upsert_record' => ZohoUpsertRecordNode::class,
            'zoho_desk_add_comment' => ZohoDeskAddCommentNode::class,
            'zoho_desk_close_ticket' => ZohoDeskCloseTicketNode::class,
            'zoho_desk_create_contact' => ZohoDeskCreateContactNode::class,
            'zoho_desk_create_ticket' => ZohoDeskCreateTicketNode::class,
            'zoho_desk_delete_ticket' => ZohoDeskDeleteTicketNode::class,
            'zoho_desk_find_contact' => ZohoDeskFindContactNode::class,
            'zoho_desk_get_me' => ZohoDeskGetMeNode::class,
            'zoho_desk_get_ticket' => ZohoDeskGetTicketNode::class,
            'zoho_desk_list_agents' => ZohoDeskListAgentsNode::class,
            'zoho_desk_list_changed_tickets' => ZohoDeskListChangedTicketsNode::class,
            'zoho_desk_list_contacts' => ZohoDeskListContactsNode::class,
            'zoho_desk_list_customer_replies' => ZohoDeskListCustomerRepliesNode::class,
            'zoho_desk_list_departments' => ZohoDeskListDepartmentsNode::class,
            'zoho_desk_list_new_tickets' => ZohoDeskListNewTicketsNode::class,
            'zoho_desk_list_threads' => ZohoDeskListThreadsNode::class,
            'zoho_desk_list_tickets' => ZohoDeskListTicketsNode::class,
            'zoho_desk_search_tickets' => ZohoDeskSearchTicketsNode::class,
            'zoho_desk_send_reply' => ZohoDeskSendReplyNode::class,
            'zoho_desk_update_contact' => ZohoDeskUpdateContactNode::class,
            'zoho_desk_update_ticket' => ZohoDeskUpdateTicketNode::class,
            'zoho_books_create_bill' => ZohoBooksCreateBillNode::class,
            'zoho_books_create_customer' => ZohoBooksCreateCustomerNode::class,
            'zoho_books_create_estimate' => ZohoBooksCreateEstimateNode::class,
            'zoho_books_create_invoice' => ZohoBooksCreateInvoiceNode::class,
            'zoho_books_create_item' => ZohoBooksCreateItemNode::class,
            'zoho_books_create_vendor' => ZohoBooksCreateVendorNode::class,
            'zoho_books_delete_invoice' => ZohoBooksDeleteInvoiceNode::class,
            'zoho_books_email_estimate' => ZohoBooksEmailEstimateNode::class,
            'zoho_books_email_invoice' => ZohoBooksEmailInvoiceNode::class,
            'zoho_books_get_bill' => ZohoBooksGetBillNode::class,
            'zoho_books_get_contact' => ZohoBooksGetContactNode::class,
            'zoho_books_get_invoice' => ZohoBooksGetInvoiceNode::class,
            'zoho_books_get_organization' => ZohoBooksGetOrganizationNode::class,
            'zoho_books_list_bills' => ZohoBooksListBillsNode::class,
            'zoho_books_list_contacts' => ZohoBooksListContactsNode::class,
            'zoho_books_list_estimates' => ZohoBooksListEstimatesNode::class,
            'zoho_books_list_invoices' => ZohoBooksListInvoicesNode::class,
            'zoho_books_list_items' => ZohoBooksListItemsNode::class,
            'zoho_books_list_new_customers' => ZohoBooksListNewCustomersNode::class,
            'zoho_books_list_new_invoices' => ZohoBooksListNewInvoicesNode::class,
            'zoho_books_list_new_payments' => ZohoBooksListNewPaymentsNode::class,
            'zoho_books_list_payments' => ZohoBooksListPaymentsNode::class,
            'zoho_books_mark_estimate' => ZohoBooksMarkEstimateNode::class,
            'zoho_books_mark_invoice' => ZohoBooksMarkInvoiceNode::class,
            'zoho_books_record_payment' => ZohoBooksRecordPaymentNode::class,
            'zoho_books_update_contact' => ZohoBooksUpdateContactNode::class,
            'zoho_projects_add_task_comment' => ZohoProjectsAddTaskCommentNode::class,
            'zoho_projects_create_bug' => ZohoProjectsCreateBugNode::class,
            'zoho_projects_create_milestone' => ZohoProjectsCreateMilestoneNode::class,
            'zoho_projects_create_project' => ZohoProjectsCreateProjectNode::class,
            'zoho_projects_create_task' => ZohoProjectsCreateTaskNode::class,
            'zoho_projects_get_project' => ZohoProjectsGetProjectNode::class,
            'zoho_projects_get_task' => ZohoProjectsGetTaskNode::class,
            'zoho_projects_list_bugs' => ZohoProjectsListBugsNode::class,
            'zoho_projects_list_changed_tasks' => ZohoProjectsListChangedTasksNode::class,
            'zoho_projects_list_milestones' => ZohoProjectsListMilestonesNode::class,
            'zoho_projects_list_new_bugs' => ZohoProjectsListNewBugsNode::class,
            'zoho_projects_list_new_tasks' => ZohoProjectsListNewTasksNode::class,
            'zoho_projects_list_projects' => ZohoProjectsListProjectsNode::class,
            'zoho_projects_list_tasks' => ZohoProjectsListTasksNode::class,
            'zoho_projects_list_users' => ZohoProjectsListUsersNode::class,
            'zoho_projects_log_time' => ZohoProjectsLogTimeNode::class,
            'zoho_projects_update_task' => ZohoProjectsUpdateTaskNode::class,
            'zoho_campaigns_add_subscriber' => ZohoCampaignsAddSubscriberNode::class,
            'zoho_campaigns_list_campaigns' => ZohoCampaignsListCampaignsNode::class,
            'zoho_campaigns_list_lists' => ZohoCampaignsListListsNode::class,
            'zoho_campaigns_list_new_subscribers' => ZohoCampaignsListNewSubscribersNode::class,
            'zoho_campaigns_list_subscribers' => ZohoCampaignsListSubscribersNode::class,
            'zoho_campaigns_list_unsubscribes' => ZohoCampaignsListUnsubscribesNode::class,
            'zoho_campaigns_send_campaign' => ZohoCampaignsSendCampaignNode::class,
            'zoho_campaigns_unsubscribe' => ZohoCampaignsUnsubscribeNode::class,
            'zoho_mail_get_account' => ZohoMailGetAccountNode::class,
            'zoho_mail_get_message' => ZohoMailGetMessageNode::class,
            'zoho_mail_list_folders' => ZohoMailListFoldersNode::class,
            'zoho_mail_list_messages' => ZohoMailListMessagesNode::class,
            'zoho_mail_list_new_messages' => ZohoMailListNewMessagesNode::class,
            'zoho_mail_mark_read' => ZohoMailMarkReadNode::class,
            'zoho_mail_move_message' => ZohoMailMoveMessageNode::class,
            'zoho_mail_search_messages' => ZohoMailSearchMessagesNode::class,
            'zoho_mail_send_email' => ZohoMailSendEmailNode::class,
            'firecrawl_api_request' => FirecrawlApiRequestNode::class,
            'firecrawl_cancel_agent' => FirecrawlCancelAgentNode::class,
            'firecrawl_cancel_batch_scrape' => FirecrawlCancelBatchScrapeNode::class,
            'firecrawl_cancel_crawl' => FirecrawlCancelCrawlNode::class,
            'firecrawl_create_monitor' => FirecrawlCreateMonitorNode::class,
            'firecrawl_create_session' => FirecrawlCreateSessionNode::class,
            'firecrawl_delete_monitor' => FirecrawlDeleteMonitorNode::class,
            'firecrawl_delete_session' => FirecrawlDeleteSessionNode::class,
            'firecrawl_execute_in_session' => FirecrawlExecuteInSessionNode::class,
            'firecrawl_find_related_papers' => FirecrawlFindRelatedPapersNode::class,
            'firecrawl_get_agent_status' => FirecrawlGetAgentStatusNode::class,
            'firecrawl_get_agent_trace' => FirecrawlGetAgentTraceNode::class,
            'firecrawl_get_batch_scrape_errors' => FirecrawlGetBatchScrapeErrorsNode::class,
            'firecrawl_get_batch_scrape_status' => FirecrawlGetBatchScrapeStatusNode::class,
            'firecrawl_get_crawl_errors' => FirecrawlGetCrawlErrorsNode::class,
            'firecrawl_get_crawl_status' => FirecrawlGetCrawlStatusNode::class,
            'firecrawl_get_credit_history' => FirecrawlGetCreditHistoryNode::class,
            'firecrawl_get_credit_usage' => FirecrawlGetCreditUsageNode::class,
            'firecrawl_get_extract_status' => FirecrawlGetExtractStatusNode::class,
            'firecrawl_get_monitor' => FirecrawlGetMonitorNode::class,
            'firecrawl_get_monitor_check' => FirecrawlGetMonitorCheckNode::class,
            'firecrawl_get_paper' => FirecrawlGetPaperNode::class,
            'firecrawl_get_queue_status' => FirecrawlGetQueueStatusNode::class,
            'firecrawl_get_scrape' => FirecrawlGetScrapeNode::class,
            'firecrawl_get_token_history' => FirecrawlGetTokenHistoryNode::class,
            'firecrawl_get_token_usage' => FirecrawlGetTokenUsageNode::class,
            'firecrawl_interact' => FirecrawlInteractNode::class,
            'firecrawl_list_active_crawls' => FirecrawlListActiveCrawlsNode::class,
            'firecrawl_list_activity' => FirecrawlListActivityNode::class,
            'firecrawl_list_agent_runs' => FirecrawlListAgentRunsNode::class,
            'firecrawl_list_finished_batch_scrapes' => FirecrawlListFinishedBatchScrapesNode::class,
            'firecrawl_list_finished_crawls' => FirecrawlListFinishedCrawlsNode::class,
            'firecrawl_list_finished_extracts' => FirecrawlListFinishedExtractsNode::class,
            'firecrawl_list_monitor_changes' => FirecrawlListMonitorChangesNode::class,
            'firecrawl_list_monitor_checks' => FirecrawlListMonitorChecksNode::class,
            'firecrawl_list_monitors' => FirecrawlListMonitorsNode::class,
            'firecrawl_list_parse_formats' => FirecrawlListParseFormatsNode::class,
            'firecrawl_list_sessions' => FirecrawlListSessionsNode::class,
            'firecrawl_map' => FirecrawlMapNode::class,
            'firecrawl_parse_file' => FirecrawlParseFileNode::class,
            'firecrawl_preview_crawl_params' => FirecrawlPreviewCrawlParamsNode::class,
            'firecrawl_run_data_tool' => FirecrawlRunDataToolNode::class,
            'firecrawl_run_monitor' => FirecrawlRunMonitorNode::class,
            'firecrawl_scrape' => FirecrawlScrapeNode::class,
            'firecrawl_search' => FirecrawlSearchNode::class,
            'firecrawl_search_developer' => FirecrawlSearchDeveloperNode::class,
            'firecrawl_search_government' => FirecrawlSearchGovernmentNode::class,
            'firecrawl_search_papers' => FirecrawlSearchPapersNode::class,
            'firecrawl_send_feedback' => FirecrawlSendFeedbackNode::class,
            'firecrawl_send_search_feedback' => FirecrawlSendSearchFeedbackNode::class,
            'firecrawl_start_agent' => FirecrawlStartAgentNode::class,
            'firecrawl_start_batch_scrape' => FirecrawlStartBatchScrapeNode::class,
            'firecrawl_start_crawl' => FirecrawlStartCrawlNode::class,
            'firecrawl_start_extract' => FirecrawlStartExtractNode::class,
            'firecrawl_stop_interact' => FirecrawlStopInteractNode::class,
            'firecrawl_update_monitor' => FirecrawlUpdateMonitorNode::class,
            'apify_abort_build' => ApifyAbortBuildNode::class,
            'apify_abort_run' => ApifyAbortRunNode::class,
            'apify_add_dataset_items' => ApifyAddDatasetItemsNode::class,
            'apify_api_request' => ApifyApiRequestNode::class,
            'apify_build_actor' => ApifyBuildActorNode::class,
            'apify_create_actor' => ApifyCreateActorNode::class,
            'apify_create_dataset' => ApifyCreateDatasetNode::class,
            'apify_create_schedule' => ApifyCreateScheduleNode::class,
            'apify_create_store' => ApifyCreateStoreNode::class,
            'apify_create_task' => ApifyCreateTaskNode::class,
            'apify_create_webhook' => ApifyCreateWebhookNode::class,
            'apify_delete_actor' => ApifyDeleteActorNode::class,
            'apify_delete_build' => ApifyDeleteBuildNode::class,
            'apify_delete_dataset' => ApifyDeleteDatasetNode::class,
            'apify_delete_run' => ApifyDeleteRunNode::class,
            'apify_delete_schedule' => ApifyDeleteScheduleNode::class,
            'apify_delete_store' => ApifyDeleteStoreNode::class,
            'apify_delete_store_record' => ApifyDeleteStoreRecordNode::class,
            'apify_delete_task' => ApifyDeleteTaskNode::class,
            'apify_delete_webhook' => ApifyDeleteWebhookNode::class,
            'apify_get_account' => ApifyGetAccountNode::class,
            'apify_get_actor' => ApifyGetActorNode::class,
            'apify_get_actor_version' => ApifyGetActorVersionNode::class,
            'apify_get_build' => ApifyGetBuildNode::class,
            'apify_get_build_log' => ApifyGetBuildLogNode::class,
            'apify_get_dataset' => ApifyGetDatasetNode::class,
            'apify_get_dataset_items' => ApifyGetDatasetItemsNode::class,
            'apify_get_dataset_schema' => ApifyGetDatasetSchemaNode::class,
            'apify_get_dataset_statistics' => ApifyGetDatasetStatisticsNode::class,
            'apify_get_last_actor_run' => ApifyGetLastActorRunNode::class,
            'apify_get_last_task_run' => ApifyGetLastTaskRunNode::class,
            'apify_get_limits' => ApifyGetLimitsNode::class,
            'apify_get_monthly_usage' => ApifyGetMonthlyUsageNode::class,
            'apify_get_run' => ApifyGetRunNode::class,
            'apify_get_run_log' => ApifyGetRunLogNode::class,
            'apify_get_run_results' => ApifyGetRunResultsNode::class,
            'apify_get_schedule' => ApifyGetScheduleNode::class,
            'apify_get_schedule_log' => ApifyGetScheduleLogNode::class,
            'apify_get_store' => ApifyGetStoreNode::class,
            'apify_get_store_record' => ApifyGetStoreRecordNode::class,
            'apify_get_task' => ApifyGetTaskNode::class,
            'apify_get_task_input' => ApifyGetTaskInputNode::class,
            'apify_get_webhook' => ApifyGetWebhookNode::class,
            'apify_get_webhook_dispatch' => ApifyGetWebhookDispatchNode::class,
            'apify_invoke_schedule' => ApifyInvokeScheduleNode::class,
            'apify_list_actor_builds' => ApifyListActorBuildsNode::class,
            'apify_list_actor_runs' => ApifyListActorRunsNode::class,
            'apify_list_actors' => ApifyListActorsNode::class,
            'apify_list_builds' => ApifyListBuildsNode::class,
            'apify_list_datasets' => ApifyListDatasetsNode::class,
            'apify_list_runs' => ApifyListRunsNode::class,
            'apify_list_schedules' => ApifyListSchedulesNode::class,
            'apify_list_store_keys' => ApifyListStoreKeysNode::class,
            'apify_list_stores' => ApifyListStoresNode::class,
            'apify_list_task_runs' => ApifyListTaskRunsNode::class,
            'apify_list_tasks' => ApifyListTasksNode::class,
            'apify_list_webhook_dispatches' => ApifyListWebhookDispatchesNode::class,
            'apify_list_webhooks' => ApifyListWebhooksNode::class,
            'apify_publish_task' => ApifyPublishTaskNode::class,
            'apify_reboot_run' => ApifyRebootRunNode::class,
            'apify_resurrect_run' => ApifyResurrectRunNode::class,
            'apify_run_actor' => ApifyRunActorNode::class,
            'apify_run_actor_and_get_results' => ApifyRunActorAndGetResultsNode::class,
            'apify_run_task' => ApifyRunTaskNode::class,
            'apify_run_task_and_get_results' => ApifyRunTaskAndGetResultsNode::class,
            'apify_search_store' => ApifySearchStoreNode::class,
            'apify_set_store_record' => ApifySetStoreRecordNode::class,
            'apify_test_webhook' => ApifyTestWebhookNode::class,
            'apify_unpublish_task' => ApifyUnpublishTaskNode::class,
            'apify_update_actor' => ApifyUpdateActorNode::class,
            'apify_update_actor_version' => ApifyUpdateActorVersionNode::class,
            'apify_update_dataset' => ApifyUpdateDatasetNode::class,
            'apify_update_limits' => ApifyUpdateLimitsNode::class,
            'apify_update_schedule' => ApifyUpdateScheduleNode::class,
            'apify_update_store' => ApifyUpdateStoreNode::class,
            'apify_update_task' => ApifyUpdateTaskNode::class,
            'apify_update_task_input' => ApifyUpdateTaskInputNode::class,
            'apify_update_webhook' => ApifyUpdateWebhookNode::class,
            'apify_validate_actor_input' => ApifyValidateActorInputNode::class,
            'apify_web_fetch' => ApifyWebFetchNode::class,
            'apify_web_search' => ApifyWebSearchNode::class,
            'exa_answer' => ExaAnswerNode::class,
            'exa_api_request' => ExaApiRequestNode::class,
            'exa_batch_monitors' => ExaBatchMonitorsNode::class,
            'exa_cancel_agent_run' => ExaCancelAgentRunNode::class,
            'exa_cancel_batch' => ExaCancelBatchNode::class,
            'exa_cancel_enrichment' => ExaCancelEnrichmentNode::class,
            'exa_cancel_webset' => ExaCancelWebsetNode::class,
            'exa_cancel_webset_search' => ExaCancelWebsetSearchNode::class,
            'exa_create_agent_run' => ExaCreateAgentRunNode::class,
            'exa_create_batch' => ExaCreateBatchNode::class,
            'exa_create_enrichment' => ExaCreateEnrichmentNode::class,
            'exa_create_monitor' => ExaCreateMonitorNode::class,
            'exa_create_webhook' => ExaCreateWebhookNode::class,
            'exa_create_webset' => ExaCreateWebsetNode::class,
            'exa_create_webset_monitor' => ExaCreateWebsetMonitorNode::class,
            'exa_create_webset_search' => ExaCreateWebsetSearchNode::class,
            'exa_delete_agent_run' => ExaDeleteAgentRunNode::class,
            'exa_delete_batch' => ExaDeleteBatchNode::class,
            'exa_delete_enrichment' => ExaDeleteEnrichmentNode::class,
            'exa_delete_monitor' => ExaDeleteMonitorNode::class,
            'exa_delete_webhook' => ExaDeleteWebhookNode::class,
            'exa_delete_webset' => ExaDeleteWebsetNode::class,
            'exa_delete_webset_item' => ExaDeleteWebsetItemNode::class,
            'exa_delete_webset_monitor' => ExaDeleteWebsetMonitorNode::class,
            'exa_find_similar' => ExaFindSimilarNode::class,
            'exa_get_agent_run' => ExaGetAgentRunNode::class,
            'exa_get_batch' => ExaGetBatchNode::class,
            'exa_get_contents' => ExaGetContentsNode::class,
            'exa_get_enrichment' => ExaGetEnrichmentNode::class,
            'exa_get_event' => ExaGetEventNode::class,
            'exa_get_monitor' => ExaGetMonitorNode::class,
            'exa_get_monitor_run' => ExaGetMonitorRunNode::class,
            'exa_get_team' => ExaGetTeamNode::class,
            'exa_get_webset' => ExaGetWebsetNode::class,
            'exa_get_webset_item' => ExaGetWebsetItemNode::class,
            'exa_get_webset_monitor' => ExaGetWebsetMonitorNode::class,
            'exa_get_webset_search' => ExaGetWebsetSearchNode::class,
            'exa_list_agent_run_events' => ExaListAgentRunEventsNode::class,
            'exa_list_agent_runs' => ExaListAgentRunsNode::class,
            'exa_list_batches' => ExaListBatchesNode::class,
            'exa_list_events' => ExaListEventsNode::class,
            'exa_list_monitor_runs' => ExaListMonitorRunsNode::class,
            'exa_list_monitors' => ExaListMonitorsNode::class,
            'exa_list_webhooks' => ExaListWebhooksNode::class,
            'exa_list_webset_items' => ExaListWebsetItemsNode::class,
            'exa_list_webset_monitors' => ExaListWebsetMonitorsNode::class,
            'exa_list_websets' => ExaListWebsetsNode::class,
            'exa_preview_webset' => ExaPreviewWebsetNode::class,
            'exa_search' => ExaSearchNode::class,
            'exa_stop_agent_run' => ExaStopAgentRunNode::class,
            'exa_trigger_monitor' => ExaTriggerMonitorNode::class,
            'exa_update_enrichment' => ExaUpdateEnrichmentNode::class,
            'exa_update_monitor' => ExaUpdateMonitorNode::class,
            'exa_update_webset' => ExaUpdateWebsetNode::class,
            'exa_update_webset_monitor' => ExaUpdateWebsetMonitorNode::class,
            'perplexity_api_request' => PerplexityApiRequestNode::class,
            'perplexity_ask' => PerplexityAskNode::class,
            'perplexity_cancel_agent_response' => PerplexityCancelAgentResponseNode::class,
            'perplexity_chat_completion' => PerplexityChatCompletionNode::class,
            'perplexity_create_agent_response' => PerplexityCreateAgentResponseNode::class,
            'perplexity_create_contextualized_embeddings' => PerplexityCreateContextualizedEmbeddingsNode::class,
            'perplexity_create_embeddings' => PerplexityCreateEmbeddingsNode::class,
            'perplexity_get_agent_response' => PerplexityGetAgentResponseNode::class,
            'perplexity_list_agent_files' => PerplexityListAgentFilesNode::class,
            'perplexity_list_models' => PerplexityListModelsNode::class,
            'perplexity_list_orchestrator_models' => PerplexityListOrchestratorModelsNode::class,
            'perplexity_orchestrator_chat' => PerplexityOrchestratorChatNode::class,
            'perplexity_reason' => PerplexityReasonNode::class,
            'perplexity_search' => PerplexitySearchNode::class,
            'perplexity_start_agent_response' => PerplexityStartAgentResponseNode::class,
            'perplexity_start_research' => PerplexityStartResearchNode::class,
            'tavily_api_request' => TavilyApiRequestNode::class,
            'tavily_crawl' => TavilyCrawlNode::class,
            'tavily_extract' => TavilyExtractNode::class,
            'tavily_get_logs' => TavilyGetLogsNode::class,
            'tavily_get_research' => TavilyGetResearchNode::class,
            'tavily_get_usage' => TavilyGetUsageNode::class,
            'tavily_map' => TavilyMapNode::class,
            'tavily_search' => TavilySearchNode::class,
            'tavily_send_feedback' => TavilySendFeedbackNode::class,
            'tavily_start_research' => TavilyStartResearchNode::class,
            'zoho_cliq_list_channels' => ZohoCliqListChannelsNode::class,
            'zoho_cliq_list_messages' => ZohoCliqListMessagesNode::class,
            'zoho_cliq_list_users' => ZohoCliqListUsersNode::class,
            'zoho_cliq_send_channel_message' => ZohoCliqSendChannelMessageNode::class,
            'zoho_cliq_send_chat_message' => ZohoCliqSendChatMessageNode::class,
            'zoho_cliq_send_direct_message' => ZohoCliqSendDirectMessageNode::class,
            'apollo_activate_sequence' => ApolloActivateSequenceNode::class,
            'apollo_add_contacts_to_sequence' => ApolloAddContactsToSequenceNode::class,
            'apollo_add_to_list' => ApolloAddToListNode::class,
            'apollo_archive_sequence' => ApolloArchiveSequenceNode::class,
            'apollo_bulk_create_accounts' => ApolloBulkCreateAccountsNode::class,
            'apollo_bulk_create_contacts' => ApolloBulkCreateContactsNode::class,
            'apollo_bulk_create_tasks' => ApolloBulkCreateTasksNode::class,
            'apollo_bulk_enrich_organizations' => ApolloBulkEnrichOrganizationsNode::class,
            'apollo_bulk_enrich_people' => ApolloBulkEnrichPeopleNode::class,
            'apollo_bulk_update_accounts' => ApolloBulkUpdateAccountsNode::class,
            'apollo_bulk_update_contacts' => ApolloBulkUpdateContactsNode::class,
            'apollo_check_email_status' => ApolloCheckEmailStatusNode::class,
            'apollo_complete_task' => ApolloCompleteTaskNode::class,
            'apollo_create_account' => ApolloCreateAccountNode::class,
            'apollo_create_call' => ApolloCreateCallNode::class,
            'apollo_create_contact' => ApolloCreateContactNode::class,
            'apollo_create_custom_field' => ApolloCreateCustomFieldNode::class,
            'apollo_create_deal' => ApolloCreateDealNode::class,
            'apollo_create_email_draft' => ApolloCreateEmailDraftNode::class,
            'apollo_create_list' => ApolloCreateListNode::class,
            'apollo_create_sequence' => ApolloCreateSequenceNode::class,
            'apollo_create_task' => ApolloCreateTaskNode::class,
            'apollo_deactivate_sequence' => ApolloDeactivateSequenceNode::class,
            'apollo_enrich_organization' => ApolloEnrichOrganizationNode::class,
            'apollo_enrich_person' => ApolloEnrichPersonNode::class,
            'apollo_export_conversations' => ApolloExportConversationsNode::class,
            'apollo_find_contact' => ApolloFindContactNode::class,
            'apollo_find_or_create_contact' => ApolloFindOrCreateContactNode::class,
            'apollo_get_account' => ApolloGetAccountNode::class,
            'apollo_get_api_usage' => ApolloGetApiUsageNode::class,
            'apollo_get_contact' => ApolloGetContactNode::class,
            'apollo_get_contact_sequence_activity' => ApolloGetContactSequenceActivityNode::class,
            'apollo_get_conversation' => ApolloGetConversationNode::class,
            'apollo_get_conversation_export' => ApolloGetConversationExportNode::class,
            'apollo_get_credit_usage' => ApolloGetCreditUsageNode::class,
            'apollo_get_current_user' => ApolloGetCurrentUserNode::class,
            'apollo_get_deal' => ApolloGetDealNode::class,
            'apollo_get_email_content' => ApolloGetEmailContentNode::class,
            'apollo_get_email_stats' => ApolloGetEmailStatsNode::class,
            'apollo_get_organization' => ApolloGetOrganizationNode::class,
            'apollo_get_person' => ApolloGetPersonNode::class,
            'apollo_get_task' => ApolloGetTaskNode::class,
            'apollo_get_webhook_result' => ApolloGetWebhookResultNode::class,
            'apollo_list_account_stages' => ApolloListAccountStagesNode::class,
            'apollo_list_changed_accounts' => ApolloListChangedAccountsNode::class,
            'apollo_list_changed_contacts' => ApolloListChangedContactsNode::class,
            'apollo_list_changed_deals' => ApolloListChangedDealsNode::class,
            'apollo_list_contact_deals' => ApolloListContactDealsNode::class,
            'apollo_list_contact_stages' => ApolloListContactStagesNode::class,
            'apollo_list_custom_fields' => ApolloListCustomFieldsNode::class,
            'apollo_list_deal_stages' => ApolloListDealStagesNode::class,
            'apollo_list_deals' => ApolloListDealsNode::class,
            'apollo_list_email_accounts' => ApolloListEmailAccountsNode::class,
            'apollo_list_email_schedules' => ApolloListEmailSchedulesNode::class,
            'apollo_list_fields' => ApolloListFieldsNode::class,
            'apollo_list_job_postings' => ApolloListJobPostingsNode::class,
            'apollo_list_lists' => ApolloListListsNode::class,
            'apollo_list_new_accounts' => ApolloListNewAccountsNode::class,
            'apollo_list_new_contacts' => ApolloListNewContactsNode::class,
            'apollo_list_new_deals' => ApolloListNewDealsNode::class,
            'apollo_list_notes' => ApolloListNotesNode::class,
            'apollo_list_users' => ApolloListUsersNode::class,
            'apollo_poll_assistant_task' => ApolloPollAssistantTaskNode::class,
            'apollo_query_report' => ApolloQueryReportNode::class,
            'apollo_remove_from_list' => ApolloRemoveFromListNode::class,
            'apollo_run_assistant_task' => ApolloRunAssistantTaskNode::class,
            'apollo_search_accounts' => ApolloSearchAccountsNode::class,
            'apollo_search_calls' => ApolloSearchCallsNode::class,
            'apollo_search_contacts' => ApolloSearchContactsNode::class,
            'apollo_search_conversations' => ApolloSearchConversationsNode::class,
            'apollo_search_emails' => ApolloSearchEmailsNode::class,
            'apollo_search_news' => ApolloSearchNewsNode::class,
            'apollo_search_organizations' => ApolloSearchOrganizationsNode::class,
            'apollo_search_people' => ApolloSearchPeopleNode::class,
            'apollo_search_sequences' => ApolloSearchSequencesNode::class,
            'apollo_search_tasks' => ApolloSearchTasksNode::class,
            'apollo_send_email' => ApolloSendEmailNode::class,
            'apollo_skip_task' => ApolloSkipTaskNode::class,
            'apollo_update_account' => ApolloUpdateAccountNode::class,
            'apollo_update_account_owners' => ApolloUpdateAccountOwnersNode::class,
            'apollo_update_account_stage' => ApolloUpdateAccountStageNode::class,
            'apollo_update_call' => ApolloUpdateCallNode::class,
            'apollo_update_contact' => ApolloUpdateContactNode::class,
            'apollo_update_contact_owners' => ApolloUpdateContactOwnersNode::class,
            'apollo_update_contact_stages' => ApolloUpdateContactStagesNode::class,
            'apollo_update_custom_field' => ApolloUpdateCustomFieldNode::class,
            'apollo_update_deal' => ApolloUpdateDealNode::class,
            'apollo_update_list' => ApolloUpdateListNode::class,
            'apollo_update_sequence' => ApolloUpdateSequenceNode::class,
            'apollo_update_sequence_contact_status' => ApolloUpdateSequenceContactStatusNode::class,
            'apollo_update_task' => ApolloUpdateTaskNode::class,
            'apollo_upsert_contact' => ApolloUpsertContactNode::class,
            'apollo_api_request' => ApolloApiRequestNode::class,
            'linkedin_ads_add_account_user' => LinkedInAdsAddAccountUserNode::class,
            'linkedin_ads_add_company_to_audience' => LinkedInAdsAddCompanyToAudienceNode::class,
            'linkedin_ads_add_contact_to_audience' => LinkedInAdsAddContactToAudienceNode::class,
            'linkedin_ads_add_conversion_to_campaign' => LinkedInAdsAddConversionToCampaignNode::class,
            'linkedin_ads_create_account' => LinkedInAdsCreateAccountNode::class,
            'linkedin_ads_create_audience' => LinkedInAdsCreateAudienceNode::class,
            'linkedin_ads_create_campaign' => LinkedInAdsCreateCampaignNode::class,
            'linkedin_ads_create_campaign_group' => LinkedInAdsCreateCampaignGroupNode::class,
            'linkedin_ads_create_conversion' => LinkedInAdsCreateConversionNode::class,
            'linkedin_ads_create_creative' => LinkedInAdsCreateCreativeNode::class,
            'linkedin_ads_create_inline_creative' => LinkedInAdsCreateInlineCreativeNode::class,
            'linkedin_ads_create_lead_form' => LinkedInAdsCreateLeadFormNode::class,
            'linkedin_ads_delete_audience' => LinkedInAdsDeleteAudienceNode::class,
            'linkedin_ads_delete_campaign' => LinkedInAdsDeleteCampaignNode::class,
            'linkedin_ads_delete_campaign_group' => LinkedInAdsDeleteCampaignGroupNode::class,
            'linkedin_ads_delete_creative' => LinkedInAdsDeleteCreativeNode::class,
            'linkedin_ads_get_account' => LinkedInAdsGetAccountNode::class,
            'linkedin_ads_get_analytics' => LinkedInAdsGetAnalyticsNode::class,
            'linkedin_ads_get_attributed_revenue' => LinkedInAdsGetAttributedRevenueNode::class,
            'linkedin_ads_get_audience' => LinkedInAdsGetAudienceNode::class,
            'linkedin_ads_get_campaign' => LinkedInAdsGetCampaignNode::class,
            'linkedin_ads_get_campaign_group' => LinkedInAdsGetCampaignGroupNode::class,
            'linkedin_ads_get_conversion' => LinkedInAdsGetConversionNode::class,
            'linkedin_ads_get_creative' => LinkedInAdsGetCreativeNode::class,
            'linkedin_ads_get_lead' => LinkedInAdsGetLeadNode::class,
            'linkedin_ads_get_lead_form' => LinkedInAdsGetLeadFormNode::class,
            'linkedin_ads_get_statistics' => LinkedInAdsGetStatisticsNode::class,
            'linkedin_ads_list_account_users' => LinkedInAdsListAccountUsersNode::class,
            'linkedin_ads_list_audiences' => LinkedInAdsListAudiencesNode::class,
            'linkedin_ads_list_campaign_conversions' => LinkedInAdsListCampaignConversionsNode::class,
            'linkedin_ads_list_changed_campaign_groups' => LinkedInAdsListChangedCampaignGroupsNode::class,
            'linkedin_ads_list_changed_campaigns' => LinkedInAdsListChangedCampaignsNode::class,
            'linkedin_ads_list_changed_creatives' => LinkedInAdsListChangedCreativesNode::class,
            'linkedin_ads_list_conversions' => LinkedInAdsListConversionsNode::class,
            'linkedin_ads_list_lead_forms' => LinkedInAdsListLeadFormsNode::class,
            'linkedin_ads_list_leads' => LinkedInAdsListLeadsNode::class,
            'linkedin_ads_list_my_accounts' => LinkedInAdsListMyAccountsNode::class,
            'linkedin_ads_list_new_campaign_groups' => LinkedInAdsListNewCampaignGroupsNode::class,
            'linkedin_ads_list_new_campaigns' => LinkedInAdsListNewCampaignsNode::class,
            'linkedin_ads_list_new_creatives' => LinkedInAdsListNewCreativesNode::class,
            'linkedin_ads_list_new_event_leads' => LinkedInAdsListNewEventLeadsNode::class,
            'linkedin_ads_list_new_lead_forms' => LinkedInAdsListNewLeadFormsNode::class,
            'linkedin_ads_list_new_leads' => LinkedInAdsListNewLeadsNode::class,
            'linkedin_ads_list_new_page_leads' => LinkedInAdsListNewPageLeadsNode::class,
            'linkedin_ads_preview_creative' => LinkedInAdsPreviewCreativeNode::class,
            'linkedin_ads_remove_account_user' => LinkedInAdsRemoveAccountUserNode::class,
            'linkedin_ads_remove_company_from_audience' => LinkedInAdsRemoveCompanyFromAudienceNode::class,
            'linkedin_ads_remove_contact_from_audience' => LinkedInAdsRemoveContactFromAudienceNode::class,
            'linkedin_ads_remove_conversion_from_campaign' => LinkedInAdsRemoveConversionFromCampaignNode::class,
            'linkedin_ads_search_accounts' => LinkedInAdsSearchAccountsNode::class,
            'linkedin_ads_search_campaign_groups' => LinkedInAdsSearchCampaignGroupsNode::class,
            'linkedin_ads_search_campaigns' => LinkedInAdsSearchCampaignsNode::class,
            'linkedin_ads_search_creatives' => LinkedInAdsSearchCreativesNode::class,
            'linkedin_ads_send_conversion_event' => LinkedInAdsSendConversionEventNode::class,
            'linkedin_ads_set_campaign_status' => LinkedInAdsSetCampaignStatusNode::class,
            'linkedin_ads_update_account' => LinkedInAdsUpdateAccountNode::class,
            'linkedin_ads_update_audience' => LinkedInAdsUpdateAudienceNode::class,
            'linkedin_ads_update_campaign' => LinkedInAdsUpdateCampaignNode::class,
            'linkedin_ads_update_campaign_group' => LinkedInAdsUpdateCampaignGroupNode::class,
            'linkedin_ads_update_conversion' => LinkedInAdsUpdateConversionNode::class,
            'linkedin_ads_update_creative' => LinkedInAdsUpdateCreativeNode::class,
            'linkedin_ads_update_lead_form' => LinkedInAdsUpdateLeadFormNode::class,
            'linkedin_ads_api_request' => LinkedInAdsApiRequestNode::class,
            'meta_ads_add_email_to_audience' => MetaAdsAddEmailToAudienceNode::class,
            'meta_ads_add_people_to_audience' => MetaAdsAddPeopleToAudienceNode::class,
            'meta_ads_archive_ad' => MetaAdsArchiveAdNode::class,
            'meta_ads_archive_ad_set' => MetaAdsArchiveAdSetNode::class,
            'meta_ads_archive_campaign' => MetaAdsArchiveCampaignNode::class,
            'meta_ads_archive_lead_form' => MetaAdsArchiveLeadFormNode::class,
            'meta_ads_copy_ad' => MetaAdsCopyAdNode::class,
            'meta_ads_copy_ad_set' => MetaAdsCopyAdSetNode::class,
            'meta_ads_copy_campaign' => MetaAdsCopyCampaignNode::class,
            'meta_ads_create_ad' => MetaAdsCreateAdNode::class,
            'meta_ads_create_ad_set' => MetaAdsCreateAdSetNode::class,
            'meta_ads_create_ads' => MetaAdsCreateAdsNode::class,
            'meta_ads_create_audience' => MetaAdsCreateAudienceNode::class,
            'meta_ads_create_campaign' => MetaAdsCreateCampaignNode::class,
            'meta_ads_create_catalog' => MetaAdsCreateCatalogNode::class,
            'meta_ads_create_creative' => MetaAdsCreateCreativeNode::class,
            'meta_ads_create_lead_form' => MetaAdsCreateLeadFormNode::class,
            'meta_ads_create_lookalike' => MetaAdsCreateLookalikeNode::class,
            'meta_ads_create_pixel' => MetaAdsCreatePixelNode::class,
            'meta_ads_delete_ad' => MetaAdsDeleteAdNode::class,
            'meta_ads_delete_ad_set' => MetaAdsDeleteAdSetNode::class,
            'meta_ads_delete_audience' => MetaAdsDeleteAudienceNode::class,
            'meta_ads_delete_campaign' => MetaAdsDeleteCampaignNode::class,
            'meta_ads_delete_campaigns' => MetaAdsDeleteCampaignsNode::class,
            'meta_ads_delete_creative' => MetaAdsDeleteCreativeNode::class,
            'meta_ads_delete_image' => MetaAdsDeleteImageNode::class,
            'meta_ads_get_account' => MetaAdsGetAccountNode::class,
            'meta_ads_get_ad' => MetaAdsGetAdNode::class,
            'meta_ads_get_ad_set' => MetaAdsGetAdSetNode::class,
            'meta_ads_get_audience' => MetaAdsGetAudienceNode::class,
            'meta_ads_get_campaign' => MetaAdsGetCampaignNode::class,
            'meta_ads_get_catalog' => MetaAdsGetCatalogNode::class,
            'meta_ads_get_catalog_diagnostics' => MetaAdsGetCatalogDiagnosticsNode::class,
            'meta_ads_get_creative' => MetaAdsGetCreativeNode::class,
            'meta_ads_get_delivery_estimate' => MetaAdsGetDeliveryEstimateNode::class,
            'meta_ads_get_insights' => MetaAdsGetInsightsNode::class,
            'meta_ads_get_insights_report' => MetaAdsGetInsightsReportNode::class,
            'meta_ads_get_lead' => MetaAdsGetLeadNode::class,
            'meta_ads_get_lead_form' => MetaAdsGetLeadFormNode::class,
            'meta_ads_get_page' => MetaAdsGetPageNode::class,
            'meta_ads_get_page_post' => MetaAdsGetPagePostNode::class,
            'meta_ads_get_pixel' => MetaAdsGetPixelNode::class,
            'meta_ads_get_pixel_stats' => MetaAdsGetPixelStatsNode::class,
            'meta_ads_get_product' => MetaAdsGetProductNode::class,
            'meta_ads_get_reach_estimate' => MetaAdsGetReachEstimateNode::class,
            'meta_ads_list_accounts' => MetaAdsListAccountsNode::class,
            'meta_ads_list_activities' => MetaAdsListActivitiesNode::class,
            'meta_ads_list_ad_leads' => MetaAdsListAdLeadsNode::class,
            'meta_ads_list_ad_sets' => MetaAdsListAdSetsNode::class,
            'meta_ads_list_ads' => MetaAdsListAdsNode::class,
            'meta_ads_list_audiences' => MetaAdsListAudiencesNode::class,
            'meta_ads_list_business_pages' => MetaAdsListBusinessPagesNode::class,
            'meta_ads_list_businesses' => MetaAdsListBusinessesNode::class,
            'meta_ads_list_campaigns' => MetaAdsListCampaignsNode::class,
            'meta_ads_list_catalogs' => MetaAdsListCatalogsNode::class,
            'meta_ads_list_changed_ad_sets' => MetaAdsListChangedAdSetsNode::class,
            'meta_ads_list_changed_ads' => MetaAdsListChangedAdsNode::class,
            'meta_ads_list_changed_campaigns' => MetaAdsListChangedCampaignsNode::class,
            'meta_ads_list_creatives' => MetaAdsListCreativesNode::class,
            'meta_ads_list_images' => MetaAdsListImagesNode::class,
            'meta_ads_list_lead_forms' => MetaAdsListLeadFormsNode::class,
            'meta_ads_list_leads' => MetaAdsListLeadsNode::class,
            'meta_ads_list_new_ad_sets' => MetaAdsListNewAdSetsNode::class,
            'meta_ads_list_new_ads' => MetaAdsListNewAdsNode::class,
            'meta_ads_list_new_campaigns' => MetaAdsListNewCampaignsNode::class,
            'meta_ads_list_new_leads' => MetaAdsListNewLeadsNode::class,
            'meta_ads_list_page_posts' => MetaAdsListPagePostsNode::class,
            'meta_ads_list_pages' => MetaAdsListPagesNode::class,
            'meta_ads_list_pixels' => MetaAdsListPixelsNode::class,
            'meta_ads_list_post_comments' => MetaAdsListPostCommentsNode::class,
            'meta_ads_list_post_reactions' => MetaAdsListPostReactionsNode::class,
            'meta_ads_list_product_feed_rules' => MetaAdsListProductFeedRulesNode::class,
            'meta_ads_list_product_feeds' => MetaAdsListProductFeedsNode::class,
            'meta_ads_list_product_set_products' => MetaAdsListProductSetProductsNode::class,
            'meta_ads_list_product_sets' => MetaAdsListProductSetsNode::class,
            'meta_ads_list_products' => MetaAdsListProductsNode::class,
            'meta_ads_list_saved_audiences' => MetaAdsListSavedAudiencesNode::class,
            'meta_ads_list_videos' => MetaAdsListVideosNode::class,
            'meta_ads_preview_ad' => MetaAdsPreviewAdNode::class,
            'meta_ads_preview_creative' => MetaAdsPreviewCreativeNode::class,
            'meta_ads_remove_email_from_audience' => MetaAdsRemoveEmailFromAudienceNode::class,
            'meta_ads_search_targeting' => MetaAdsSearchTargetingNode::class,
            'meta_ads_send_conversion_event' => MetaAdsSendConversionEventNode::class,
            'meta_ads_send_funnel_event' => MetaAdsSendFunnelEventNode::class,
            'meta_ads_send_lead_event' => MetaAdsSendLeadEventNode::class,
            'meta_ads_send_purchase_event' => MetaAdsSendPurchaseEventNode::class,
            'meta_ads_set_ad_set_status' => MetaAdsSetAdSetStatusNode::class,
            'meta_ads_set_ad_status' => MetaAdsSetAdStatusNode::class,
            'meta_ads_set_campaign_status' => MetaAdsSetCampaignStatusNode::class,
            'meta_ads_start_insights_report' => MetaAdsStartInsightsReportNode::class,
            'meta_ads_update_ad' => MetaAdsUpdateAdNode::class,
            'meta_ads_update_ad_set' => MetaAdsUpdateAdSetNode::class,
            'meta_ads_update_audience' => MetaAdsUpdateAudienceNode::class,
            'meta_ads_update_campaign' => MetaAdsUpdateCampaignNode::class,
            'meta_ads_update_creative' => MetaAdsUpdateCreativeNode::class,
            'meta_ads_upload_image' => MetaAdsUploadImageNode::class,
            'meta_ads_api_request' => MetaAdsApiRequestNode::class,
            'zendesk_add_comment' => ZendeskAddCommentNode::class,
            'zendesk_add_tags' => ZendeskAddTagsNode::class,
            'zendesk_api_request' => ZendeskApiRequestNode::class,
            'zendesk_apply_macro' => ZendeskApplyMacroNode::class,
            'zendesk_attach_file_to_ticket' => ZendeskAttachFileToTicketNode::class,
            'zendesk_batch_create_tickets' => ZendeskBatchCreateTicketsNode::class,
            'zendesk_batch_update_tickets' => ZendeskBatchUpdateTicketsNode::class,
            'zendesk_count_organizations' => ZendeskCountOrganizationsNode::class,
            'zendesk_count_tickets' => ZendeskCountTicketsNode::class,
            'zendesk_count_view_tickets' => ZendeskCountViewTicketsNode::class,
            'zendesk_create_group' => ZendeskCreateGroupNode::class,
            'zendesk_create_or_update_organization' => ZendeskCreateOrUpdateOrganizationNode::class,
            'zendesk_create_or_update_user' => ZendeskCreateOrUpdateUserNode::class,
            'zendesk_create_organization' => ZendeskCreateOrganizationNode::class,
            'zendesk_create_side_conversation' => ZendeskCreateSideConversationNode::class,
            'zendesk_create_ticket' => ZendeskCreateTicketNode::class,
            'zendesk_create_user' => ZendeskCreateUserNode::class,
            'zendesk_delete_group' => ZendeskDeleteGroupNode::class,
            'zendesk_delete_organization' => ZendeskDeleteOrganizationNode::class,
            'zendesk_delete_suspended_ticket' => ZendeskDeleteSuspendedTicketNode::class,
            'zendesk_delete_ticket' => ZendeskDeleteTicketNode::class,
            'zendesk_delete_ticket_field_option' => ZendeskDeleteTicketFieldOptionNode::class,
            'zendesk_delete_ticket_permanently' => ZendeskDeleteTicketPermanentlyNode::class,
            'zendesk_delete_user' => ZendeskDeleteUserNode::class,
            'zendesk_find_agent' => ZendeskFindAgentNode::class,
            'zendesk_find_dynamic_content' => ZendeskFindDynamicContentNode::class,
            'zendesk_find_group' => ZendeskFindGroupNode::class,
            'zendesk_find_or_create_organization' => ZendeskFindOrCreateOrganizationNode::class,
            'zendesk_find_or_create_user' => ZendeskFindOrCreateUserNode::class,
            'zendesk_find_user' => ZendeskFindUserNode::class,
            'zendesk_get_article' => ZendeskGetArticleNode::class,
            'zendesk_get_attachment' => ZendeskGetAttachmentNode::class,
            'zendesk_get_audit' => ZendeskGetAuditNode::class,
            'zendesk_get_automation' => ZendeskGetAutomationNode::class,
            'zendesk_get_dynamic_content' => ZendeskGetDynamicContentNode::class,
            'zendesk_get_group' => ZendeskGetGroupNode::class,
            'zendesk_get_job_status' => ZendeskGetJobStatusNode::class,
            'zendesk_get_latest_comment' => ZendeskGetLatestCommentNode::class,
            'zendesk_get_macro' => ZendeskGetMacroNode::class,
            'zendesk_get_me' => ZendeskGetMeNode::class,
            'zendesk_get_organization' => ZendeskGetOrganizationNode::class,
            'zendesk_get_organization_related' => ZendeskGetOrganizationRelatedNode::class,
            'zendesk_get_post' => ZendeskGetPostNode::class,
            'zendesk_get_side_conversation' => ZendeskGetSideConversationNode::class,
            'zendesk_get_talk_recordings' => ZendeskGetTalkRecordingsNode::class,
            'zendesk_get_ticket' => ZendeskGetTicketNode::class,
            'zendesk_get_ticket_field' => ZendeskGetTicketFieldNode::class,
            'zendesk_get_ticket_metrics' => ZendeskGetTicketMetricsNode::class,
            'zendesk_get_trigger' => ZendeskGetTriggerNode::class,
            'zendesk_get_user' => ZendeskGetUserNode::class,
            'zendesk_get_user_related' => ZendeskGetUserRelatedNode::class,
            'zendesk_get_view' => ZendeskGetViewNode::class,
            'zendesk_list_active_macros' => ZendeskListActiveMacrosNode::class,
            'zendesk_list_article_comments' => ZendeskListArticleCommentsNode::class,
            'zendesk_list_articles' => ZendeskListArticlesNode::class,
            'zendesk_list_attachments' => ZendeskListAttachmentsNode::class,
            'zendesk_list_audits' => ZendeskListAuditsNode::class,
            'zendesk_list_automations' => ZendeskListAutomationsNode::class,
            'zendesk_list_closed_tickets' => ZendeskListClosedTicketsNode::class,
            'zendesk_list_comments' => ZendeskListCommentsNode::class,
            'zendesk_list_deleted_tickets' => ZendeskListDeletedTicketsNode::class,
            'zendesk_list_dynamic_content' => ZendeskListDynamicContentNode::class,
            'zendesk_list_groups' => ZendeskListGroupsNode::class,
            'zendesk_list_locales' => ZendeskListLocalesNode::class,
            'zendesk_list_macro_categories' => ZendeskListMacroCategoriesNode::class,
            'zendesk_list_macros' => ZendeskListMacrosNode::class,
            'zendesk_list_new_organizations' => ZendeskListNewOrganizationsNode::class,
            'zendesk_list_new_tickets' => ZendeskListNewTicketsNode::class,
            'zendesk_list_new_users' => ZendeskListNewUsersNode::class,
            'zendesk_list_organizations' => ZendeskListOrganizationsNode::class,
            'zendesk_list_pending_tickets' => ZendeskListPendingTicketsNode::class,
            'zendesk_list_post_comments' => ZendeskListPostCommentsNode::class,
            'zendesk_list_posts' => ZendeskListPostsNode::class,
            'zendesk_list_recent_actions' => ZendeskListRecentActionsNode::class,
            'zendesk_list_recent_comments' => ZendeskListRecentCommentsNode::class,
            'zendesk_list_side_conversation_events' => ZendeskListSideConversationEventsNode::class,
            'zendesk_list_side_conversations' => ZendeskListSideConversationsNode::class,
            'zendesk_list_solved_tickets' => ZendeskListSolvedTicketsNode::class,
            'zendesk_list_suspended_tickets' => ZendeskListSuspendedTicketsNode::class,
            'zendesk_list_tagged_tickets' => ZendeskListTaggedTicketsNode::class,
            'zendesk_list_tagged_users' => ZendeskListTaggedUsersNode::class,
            'zendesk_list_tags' => ZendeskListTagsNode::class,
            'zendesk_list_talk_call_legs' => ZendeskListTalkCallLegsNode::class,
            'zendesk_list_talk_calls' => ZendeskListTalkCallsNode::class,
            'zendesk_list_ticket_field_options' => ZendeskListTicketFieldOptionsNode::class,
            'zendesk_list_ticket_fields' => ZendeskListTicketFieldsNode::class,
            'zendesk_list_ticket_forms' => ZendeskListTicketFormsNode::class,
            'zendesk_list_tickets' => ZendeskListTicketsNode::class,
            'zendesk_list_trigger_categories' => ZendeskListTriggerCategoriesNode::class,
            'zendesk_list_triggers' => ZendeskListTriggersNode::class,
            'zendesk_list_updated_organizations' => ZendeskListUpdatedOrganizationsNode::class,
            'zendesk_list_updated_tickets' => ZendeskListUpdatedTicketsNode::class,
            'zendesk_list_updated_users' => ZendeskListUpdatedUsersNode::class,
            'zendesk_list_user_organizations' => ZendeskListUserOrganizationsNode::class,
            'zendesk_list_users' => ZendeskListUsersNode::class,
            'zendesk_list_view_tickets' => ZendeskListViewTicketsNode::class,
            'zendesk_list_view_updated_tickets' => ZendeskListViewUpdatedTicketsNode::class,
            'zendesk_list_views' => ZendeskListViewsNode::class,
            'zendesk_make_comment_private' => ZendeskMakeCommentPrivateNode::class,
            'zendesk_merge_tickets' => ZendeskMergeTicketsNode::class,
            'zendesk_preview_macro' => ZendeskPreviewMacroNode::class,
            'zendesk_recover_suspended_ticket' => ZendeskRecoverSuspendedTicketNode::class,
            'zendesk_remove_tags' => ZendeskRemoveTagsNode::class,
            'zendesk_reply_side_conversation' => ZendeskReplySideConversationNode::class,
            'zendesk_restore_ticket' => ZendeskRestoreTicketNode::class,
            'zendesk_save_ticket_field_option' => ZendeskSaveTicketFieldOptionNode::class,
            'zendesk_search' => ZendeskSearchAnythingNode::class,
            'zendesk_search_articles' => ZendeskSearchArticlesNode::class,
            'zendesk_search_automations' => ZendeskSearchAutomationsNode::class,
            'zendesk_search_help_center' => ZendeskSearchHelpCenterNode::class,
            'zendesk_search_macros' => ZendeskSearchMacrosNode::class,
            'zendesk_search_organizations' => ZendeskSearchOrganizationsNode::class,
            'zendesk_search_posts' => ZendeskSearchPostsNode::class,
            'zendesk_search_tags' => ZendeskSearchTagsNode::class,
            'zendesk_search_tickets' => ZendeskSearchTicketsNode::class,
            'zendesk_search_triggers' => ZendeskSearchTriggersNode::class,
            'zendesk_search_users' => ZendeskSearchUsersNode::class,
            'zendesk_set_tags' => ZendeskSetTagsNode::class,
            'zendesk_update_group' => ZendeskUpdateGroupNode::class,
            'zendesk_update_organization' => ZendeskUpdateOrganizationNode::class,
            'zendesk_update_side_conversation' => ZendeskUpdateSideConversationNode::class,
            'zendesk_update_ticket' => ZendeskUpdateTicketNode::class,
            'zendesk_update_user' => ZendeskUpdateUserNode::class,
            'zendesk_upload_attachment' => ZendeskUploadAttachmentNode::class,
            'intercom_api_request' => IntercomApiRequestNode::class,
            'intercom_archive_contact' => IntercomArchiveContactNode::class,
            'intercom_assign_conversation' => IntercomAssignConversationNode::class,
            'intercom_attach_contact_company' => IntercomAttachContactCompanyNode::class,
            'intercom_attach_conversation_contact' => IntercomAttachConversationContactNode::class,
            'intercom_block_contact' => IntercomBlockContactNode::class,
            'intercom_close_conversation' => IntercomCloseConversationNode::class,
            'intercom_convert_conversation' => IntercomConvertConversationNode::class,
            'intercom_create_article' => IntercomCreateArticleNode::class,
            'intercom_create_company' => IntercomCreateCompanyNode::class,
            'intercom_create_contact' => IntercomCreateContactNode::class,
            'intercom_create_contact_note' => IntercomCreateContactNoteNode::class,
            'intercom_create_conversation' => IntercomCreateConversationNode::class,
            'intercom_create_data_attribute' => IntercomCreateDataAttributeNode::class,
            'intercom_create_tag' => IntercomCreateTagNode::class,
            'intercom_create_ticket' => IntercomCreateTicketNode::class,
            'intercom_delete_article' => IntercomDeleteArticleNode::class,
            'intercom_delete_company' => IntercomDeleteCompanyNode::class,
            'intercom_delete_contact' => IntercomDeleteContactNode::class,
            'intercom_delete_tag' => IntercomDeleteTagNode::class,
            'intercom_detach_contact_company' => IntercomDetachContactCompanyNode::class,
            'intercom_detach_conversation_contact' => IntercomDetachConversationContactNode::class,
            'intercom_find_company' => IntercomFindCompanyNode::class,
            'intercom_find_contact_by_external_id' => IntercomFindContactByExternalIdNode::class,
            'intercom_find_lead' => IntercomFindLeadNode::class,
            'intercom_find_or_create_company' => IntercomFindOrCreateCompanyNode::class,
            'intercom_find_or_create_lead' => IntercomFindOrCreateLeadNode::class,
            'intercom_find_user' => IntercomFindUserNode::class,
            'intercom_get_admin' => IntercomGetAdminNode::class,
            'intercom_get_article' => IntercomGetArticleNode::class,
            'intercom_get_company' => IntercomGetCompanyNode::class,
            'intercom_get_contact' => IntercomGetContactNode::class,
            'intercom_get_conversation' => IntercomGetConversationNode::class,
            'intercom_get_job_status' => IntercomGetJobStatusNode::class,
            'intercom_get_me' => IntercomGetMeNode::class,
            'intercom_get_note' => IntercomGetNoteNode::class,
            'intercom_get_segment' => IntercomGetSegmentNode::class,
            'intercom_get_tag' => IntercomGetTagNode::class,
            'intercom_get_team' => IntercomGetTeamNode::class,
            'intercom_get_ticket' => IntercomGetTicketNode::class,
            'intercom_list_activity_logs' => IntercomListActivityLogsNode::class,
            'intercom_list_admin_replies' => IntercomListAdminRepliesNode::class,
            'intercom_list_admins' => IntercomListAdminsNode::class,
            'intercom_list_articles' => IntercomListArticlesNode::class,
            'intercom_list_closed_conversations' => IntercomListClosedConversationsNode::class,
            'intercom_list_collections' => IntercomListCollectionsNode::class,
            'intercom_list_companies' => IntercomListCompaniesNode::class,
            'intercom_list_company_contacts' => IntercomListCompanyContactsNode::class,
            'intercom_list_company_segments' => IntercomListCompanySegmentsNode::class,
            'intercom_list_contact_companies' => IntercomListContactCompaniesNode::class,
            'intercom_list_contact_events' => IntercomListContactEventsNode::class,
            'intercom_list_contact_notes' => IntercomListContactNotesNode::class,
            'intercom_list_contact_replies' => IntercomListContactRepliesNode::class,
            'intercom_list_contact_segments' => IntercomListContactSegmentsNode::class,
            'intercom_list_contact_subscriptions' => IntercomListContactSubscriptionsNode::class,
            'intercom_list_contact_tags' => IntercomListContactTagsNode::class,
            'intercom_list_contacts' => IntercomListContactsNode::class,
            'intercom_list_conversations' => IntercomListConversationsNode::class,
            'intercom_list_data_attributes' => IntercomListDataAttributesNode::class,
            'intercom_list_leads_with_email' => IntercomListLeadsWithEmailNode::class,
            'intercom_list_new_companies' => IntercomListNewCompaniesNode::class,
            'intercom_list_new_conversations' => IntercomListNewConversationsNode::class,
            'intercom_list_new_leads' => IntercomListNewLeadsNode::class,
            'intercom_list_new_tickets' => IntercomListNewTicketsNode::class,
            'intercom_list_new_users' => IntercomListNewUsersNode::class,
            'intercom_list_rated_conversations' => IntercomListRatedConversationsNode::class,
            'intercom_list_segments' => IntercomListSegmentsNode::class,
            'intercom_list_subscription_types' => IntercomListSubscriptionTypesNode::class,
            'intercom_list_tagged_conversations' => IntercomListTaggedConversationsNode::class,
            'intercom_list_tagged_leads' => IntercomListTaggedLeadsNode::class,
            'intercom_list_tagged_users' => IntercomListTaggedUsersNode::class,
            'intercom_list_tags' => IntercomListTagsNode::class,
            'intercom_list_teams' => IntercomListTeamsNode::class,
            'intercom_list_ticket_types' => IntercomListTicketTypesNode::class,
            'intercom_list_unsubscribes' => IntercomListUnsubscribesNode::class,
            'intercom_list_updated_companies' => IntercomListUpdatedCompaniesNode::class,
            'intercom_list_updated_contacts' => IntercomListUpdatedContactsNode::class,
            'intercom_list_updated_tickets' => IntercomListUpdatedTicketsNode::class,
            'intercom_mark_conversation_read' => IntercomMarkConversationReadNode::class,
            'intercom_merge_contacts' => IntercomMergeContactsNode::class,
            'intercom_open_conversation' => IntercomOpenConversationNode::class,
            'intercom_reply_as_contact' => IntercomReplyAsContactNode::class,
            'intercom_reply_to_conversation' => IntercomReplyToConversationNode::class,
            'intercom_reply_to_ticket' => IntercomReplyToTicketNode::class,
            'intercom_scroll_companies' => IntercomScrollCompaniesNode::class,
            'intercom_search_articles' => IntercomSearchArticlesNode::class,
            'intercom_search_companies' => IntercomSearchCompaniesNode::class,
            'intercom_search_contacts' => IntercomSearchContactsNode::class,
            'intercom_search_conversations' => IntercomSearchConversationsNode::class,
            'intercom_search_tickets' => IntercomSearchTicketsNode::class,
            'intercom_send_message' => IntercomSendMessageNode::class,
            'intercom_set_admin_away' => IntercomSetAdminAwayNode::class,
            'intercom_snooze_conversation' => IntercomSnoozeConversationNode::class,
            'intercom_submit_event' => IntercomSubmitEventNode::class,
            'intercom_tag_company' => IntercomTagCompanyNode::class,
            'intercom_tag_contact' => IntercomTagContactNode::class,
            'intercom_tag_conversation' => IntercomTagConversationNode::class,
            'intercom_tag_ticket' => IntercomTagTicketNode::class,
            'intercom_unarchive_contact' => IntercomUnarchiveContactNode::class,
            'intercom_untag_contact' => IntercomUntagContactNode::class,
            'intercom_untag_conversation' => IntercomUntagConversationNode::class,
            'intercom_untag_ticket' => IntercomUntagTicketNode::class,
            'intercom_update_article' => IntercomUpdateArticleNode::class,
            'intercom_update_company' => IntercomUpdateCompanyNode::class,
            'intercom_update_contact' => IntercomUpdateContactNode::class,
            'intercom_update_conversation' => IntercomUpdateConversationNode::class,
            'intercom_update_data_attribute' => IntercomUpdateDataAttributeNode::class,
            'intercom_update_ticket' => IntercomUpdateTicketNode::class,
            'intercom_upsert_contact' => IntercomUpsertContactNode::class,
            'freshdesk_add_note' => FreshdeskAddNoteNode::class,
            'freshdesk_add_ticket_tags' => FreshdeskAddTicketTagsNode::class,
            'freshdesk_add_watcher' => FreshdeskAddWatcherNode::class,
            'freshdesk_api_request' => FreshdeskApiRequestNode::class,
            'freshdesk_assign_ticket_to_agent' => FreshdeskAssignTicketToAgentNode::class,
            'freshdesk_assign_ticket_to_group' => FreshdeskAssignTicketToGroupNode::class,
            'freshdesk_bulk_delete_tickets' => FreshdeskBulkDeleteTicketsNode::class,
            'freshdesk_bulk_update_tickets' => FreshdeskBulkUpdateTicketsNode::class,
            'freshdesk_close_ticket' => FreshdeskCloseTicketNode::class,
            'freshdesk_create_agent' => FreshdeskCreateAgentNode::class,
            'freshdesk_create_canned_response' => FreshdeskCreateCannedResponseNode::class,
            'freshdesk_create_canned_response_folder' => FreshdeskCreateCannedResponseFolderNode::class,
            'freshdesk_create_company' => FreshdeskCreateCompanyNode::class,
            'freshdesk_create_contact' => FreshdeskCreateContactNode::class,
            'freshdesk_create_custom_object_record' => FreshdeskCreateCustomObjectRecordNode::class,
            'freshdesk_create_forum' => FreshdeskCreateForumNode::class,
            'freshdesk_create_forum_category' => FreshdeskCreateForumCategoryNode::class,
            'freshdesk_create_forum_comment' => FreshdeskCreateForumCommentNode::class,
            'freshdesk_create_forum_topic' => FreshdeskCreateForumTopicNode::class,
            'freshdesk_create_group' => FreshdeskCreateGroupNode::class,
            'freshdesk_create_outbound_email' => FreshdeskCreateOutboundEmailNode::class,
            'freshdesk_create_satisfaction_rating' => FreshdeskCreateSatisfactionRatingNode::class,
            'freshdesk_create_solution_article' => FreshdeskCreateSolutionArticleNode::class,
            'freshdesk_create_solution_category' => FreshdeskCreateSolutionCategoryNode::class,
            'freshdesk_create_solution_folder' => FreshdeskCreateSolutionFolderNode::class,
            'freshdesk_create_thread' => FreshdeskCreateThreadNode::class,
            'freshdesk_create_thread_message' => FreshdeskCreateThreadMessageNode::class,
            'freshdesk_create_ticket' => FreshdeskCreateTicketNode::class,
            'freshdesk_create_ticket_field' => FreshdeskCreateTicketFieldNode::class,
            'freshdesk_create_time_entry' => FreshdeskCreateTimeEntryNode::class,
            'freshdesk_delete_agent' => FreshdeskDeleteAgentNode::class,
            'freshdesk_delete_attachment' => FreshdeskDeleteAttachmentNode::class,
            'freshdesk_delete_company' => FreshdeskDeleteCompanyNode::class,
            'freshdesk_delete_contact' => FreshdeskDeleteContactNode::class,
            'freshdesk_delete_conversation' => FreshdeskDeleteConversationNode::class,
            'freshdesk_delete_custom_object_record' => FreshdeskDeleteCustomObjectRecordNode::class,
            'freshdesk_delete_forum' => FreshdeskDeleteForumNode::class,
            'freshdesk_delete_forum_category' => FreshdeskDeleteForumCategoryNode::class,
            'freshdesk_delete_forum_comment' => FreshdeskDeleteForumCommentNode::class,
            'freshdesk_delete_forum_topic' => FreshdeskDeleteForumTopicNode::class,
            'freshdesk_delete_group' => FreshdeskDeleteGroupNode::class,
            'freshdesk_delete_skill' => FreshdeskDeleteSkillNode::class,
            'freshdesk_delete_solution_article' => FreshdeskDeleteSolutionArticleNode::class,
            'freshdesk_delete_solution_category' => FreshdeskDeleteSolutionCategoryNode::class,
            'freshdesk_delete_solution_folder' => FreshdeskDeleteSolutionFolderNode::class,
            'freshdesk_delete_summary' => FreshdeskDeleteSummaryNode::class,
            'freshdesk_delete_thread' => FreshdeskDeleteThreadNode::class,
            'freshdesk_delete_thread_message' => FreshdeskDeleteThreadMessageNode::class,
            'freshdesk_delete_ticket' => FreshdeskDeleteTicketNode::class,
            'freshdesk_delete_ticket_field' => FreshdeskDeleteTicketFieldNode::class,
            'freshdesk_delete_time_entry' => FreshdeskDeleteTimeEntryNode::class,
            'freshdesk_find_company' => FreshdeskFindCompanyNode::class,
            'freshdesk_find_contact' => FreshdeskFindContactNode::class,
            'freshdesk_follow_forum_topic' => FreshdeskFollowForumTopicNode::class,
            'freshdesk_forward_ticket' => FreshdeskForwardTicketNode::class,
            'freshdesk_get_account' => FreshdeskGetAccountNode::class,
            'freshdesk_get_agent' => FreshdeskGetAgentNode::class,
            'freshdesk_get_associated_tickets' => FreshdeskGetAssociatedTicketsNode::class,
            'freshdesk_get_attachment' => FreshdeskGetAttachmentNode::class,
            'freshdesk_get_business_hours' => FreshdeskGetBusinessHoursNode::class,
            'freshdesk_get_canned_response' => FreshdeskGetCannedResponseNode::class,
            'freshdesk_get_canned_response_folder' => FreshdeskGetCannedResponseFolderNode::class,
            'freshdesk_get_company' => FreshdeskGetCompanyNode::class,
            'freshdesk_get_contact' => FreshdeskGetContactNode::class,
            'freshdesk_get_current_agent' => FreshdeskGetCurrentAgentNode::class,
            'freshdesk_get_custom_object_record' => FreshdeskGetCustomObjectRecordNode::class,
            'freshdesk_get_custom_object_schema' => FreshdeskGetCustomObjectSchemaNode::class,
            'freshdesk_get_email_config' => FreshdeskGetEmailConfigNode::class,
            'freshdesk_get_forum' => FreshdeskGetForumNode::class,
            'freshdesk_get_forum_category' => FreshdeskGetForumCategoryNode::class,
            'freshdesk_get_forum_topic' => FreshdeskGetForumTopicNode::class,
            'freshdesk_get_group' => FreshdeskGetGroupNode::class,
            'freshdesk_get_helpdesk_settings' => FreshdeskGetHelpdeskSettingsNode::class,
            'freshdesk_get_job_status' => FreshdeskGetJobStatusNode::class,
            'freshdesk_get_outbound_message' => FreshdeskGetOutboundMessageNode::class,
            'freshdesk_get_prime_association' => FreshdeskGetPrimeAssociationNode::class,
            'freshdesk_get_product' => FreshdeskGetProductNode::class,
            'freshdesk_get_role' => FreshdeskGetRoleNode::class,
            'freshdesk_get_skill' => FreshdeskGetSkillNode::class,
            'freshdesk_get_solution_article' => FreshdeskGetSolutionArticleNode::class,
            'freshdesk_get_solution_category' => FreshdeskGetSolutionCategoryNode::class,
            'freshdesk_get_solution_folder' => FreshdeskGetSolutionFolderNode::class,
            'freshdesk_get_thread' => FreshdeskGetThreadNode::class,
            'freshdesk_get_thread_message' => FreshdeskGetThreadMessageNode::class,
            'freshdesk_get_ticket' => FreshdeskGetTicketNode::class,
            'freshdesk_get_ticket_field' => FreshdeskGetTicketFieldNode::class,
            'freshdesk_hard_delete_contact' => FreshdeskHardDeleteContactNode::class,
            'freshdesk_list_agents' => FreshdeskListAgentsNode::class,
            'freshdesk_list_attachments' => FreshdeskListAttachmentsNode::class,
            'freshdesk_list_business_hours' => FreshdeskListBusinessHoursNode::class,
            'freshdesk_list_canned_response_folders' => FreshdeskListCannedResponseFoldersNode::class,
            'freshdesk_list_canned_responses' => FreshdeskListCannedResponsesNode::class,
            'freshdesk_list_companies' => FreshdeskListCompaniesNode::class,
            'freshdesk_list_company_fields' => FreshdeskListCompanyFieldsNode::class,
            'freshdesk_list_contact_fields' => FreshdeskListContactFieldsNode::class,
            'freshdesk_list_contacts' => FreshdeskListContactsNode::class,
            'freshdesk_list_conversations' => FreshdeskListConversationsNode::class,
            'freshdesk_list_custom_object_records' => FreshdeskListCustomObjectRecordsNode::class,
            'freshdesk_list_custom_object_schemas' => FreshdeskListCustomObjectSchemasNode::class,
            'freshdesk_list_email_configs' => FreshdeskListEmailConfigsNode::class,
            'freshdesk_list_forum_categories' => FreshdeskListForumCategoriesNode::class,
            'freshdesk_list_forum_topics' => FreshdeskListForumTopicsNode::class,
            'freshdesk_list_forums' => FreshdeskListForumsNode::class,
            'freshdesk_list_groups' => FreshdeskListGroupsNode::class,
            'freshdesk_list_new_companies' => FreshdeskListNewCompaniesNode::class,
            'freshdesk_list_new_contacts' => FreshdeskListNewContactsNode::class,
            'freshdesk_list_new_notes' => FreshdeskListNewNotesNode::class,
            'freshdesk_list_new_tickets' => FreshdeskListNewTicketsNode::class,
            'freshdesk_list_products' => FreshdeskListProductsNode::class,
            'freshdesk_list_roles' => FreshdeskListRolesNode::class,
            'freshdesk_list_satisfaction_ratings' => FreshdeskListSatisfactionRatingsNode::class,
            'freshdesk_list_skills' => FreshdeskListSkillsNode::class,
            'freshdesk_list_sla_policies' => FreshdeskListSlaPoliciesNode::class,
            'freshdesk_list_solution_articles' => FreshdeskListSolutionArticlesNode::class,
            'freshdesk_list_solution_categories' => FreshdeskListSolutionCategoriesNode::class,
            'freshdesk_list_solution_folders' => FreshdeskListSolutionFoldersNode::class,
            'freshdesk_list_solution_subfolders' => FreshdeskListSolutionSubfoldersNode::class,
            'freshdesk_list_surveys' => FreshdeskListSurveysNode::class,
            'freshdesk_list_ticket_fields' => FreshdeskListTicketFieldsNode::class,
            'freshdesk_list_ticket_forms' => FreshdeskListTicketFormsNode::class,
            'freshdesk_list_ticket_ratings' => FreshdeskListTicketRatingsNode::class,
            'freshdesk_list_tickets' => FreshdeskListTicketsNode::class,
            'freshdesk_list_time_entries' => FreshdeskListTimeEntriesNode::class,
            'freshdesk_list_topic_comments' => FreshdeskListTopicCommentsNode::class,
            'freshdesk_list_updated_contacts' => FreshdeskListUpdatedContactsNode::class,
            'freshdesk_list_updated_tickets' => FreshdeskListUpdatedTicketsNode::class,
            'freshdesk_list_watchers' => FreshdeskListWatchersNode::class,
            'freshdesk_make_agent' => FreshdeskMakeAgentNode::class,
            'freshdesk_merge_contacts' => FreshdeskMergeContactsNode::class,
            'freshdesk_merge_tickets' => FreshdeskMergeTicketsNode::class,
            'freshdesk_remove_ticket_tags' => FreshdeskRemoveTicketTagsNode::class,
            'freshdesk_remove_watcher' => FreshdeskRemoveWatcherNode::class,
            'freshdesk_reply_to_forward' => FreshdeskReplyToForwardNode::class,
            'freshdesk_reply_to_ticket' => FreshdeskReplyToTicketNode::class,
            'freshdesk_restore_contact' => FreshdeskRestoreContactNode::class,
            'freshdesk_restore_ticket' => FreshdeskRestoreTicketNode::class,
            'freshdesk_search_companies' => FreshdeskSearchCompaniesNode::class,
            'freshdesk_search_contacts' => FreshdeskSearchContactsNode::class,
            'freshdesk_search_solutions' => FreshdeskSearchSolutionsNode::class,
            'freshdesk_search_tickets' => FreshdeskSearchTicketsNode::class,
            'freshdesk_send_invite' => FreshdeskSendInviteNode::class,
            'freshdesk_set_ticket_priority' => FreshdeskSetTicketPriorityNode::class,
            'freshdesk_set_ticket_status' => FreshdeskSetTicketStatusNode::class,
            'freshdesk_set_ticket_tags' => FreshdeskSetTicketTagsNode::class,
            'freshdesk_toggle_timer' => FreshdeskToggleTimerNode::class,
            'freshdesk_unfollow_forum_topic' => FreshdeskUnfollowForumTopicNode::class,
            'freshdesk_update_agent' => FreshdeskUpdateAgentNode::class,
            'freshdesk_update_canned_response' => FreshdeskUpdateCannedResponseNode::class,
            'freshdesk_update_company' => FreshdeskUpdateCompanyNode::class,
            'freshdesk_update_contact' => FreshdeskUpdateContactNode::class,
            'freshdesk_update_conversation' => FreshdeskUpdateConversationNode::class,
            'freshdesk_update_custom_object_record' => FreshdeskUpdateCustomObjectRecordNode::class,
            'freshdesk_update_forum' => FreshdeskUpdateForumNode::class,
            'freshdesk_update_forum_category' => FreshdeskUpdateForumCategoryNode::class,
            'freshdesk_update_forum_comment' => FreshdeskUpdateForumCommentNode::class,
            'freshdesk_update_forum_topic' => FreshdeskUpdateForumTopicNode::class,
            'freshdesk_update_group' => FreshdeskUpdateGroupNode::class,
            'freshdesk_update_solution_article' => FreshdeskUpdateSolutionArticleNode::class,
            'freshdesk_update_solution_category' => FreshdeskUpdateSolutionCategoryNode::class,
            'freshdesk_update_solution_folder' => FreshdeskUpdateSolutionFolderNode::class,
            'freshdesk_update_summary' => FreshdeskUpdateSummaryNode::class,
            'freshdesk_update_thread' => FreshdeskUpdateThreadNode::class,
            'freshdesk_update_thread_message' => FreshdeskUpdateThreadMessageNode::class,
            'freshdesk_update_ticket' => FreshdeskUpdateTicketNode::class,
            'freshdesk_update_ticket_field' => FreshdeskUpdateTicketFieldNode::class,
            'freshdesk_update_time_entry' => FreshdeskUpdateTimeEntryNode::class,
            'freshdesk_view_summary' => FreshdeskViewSummaryNode::class,
            'datadog_add_host_tags' => DatadogAddHostTagsNode::class,
            'datadog_add_team_member' => DatadogAddTeamMemberNode::class,
            'datadog_add_to_dashboard_list' => DatadogAddToDashboardListNode::class,
            'datadog_aggregate_logs' => DatadogAggregateLogsNode::class,
            'datadog_aggregate_rum_events' => DatadogAggregateRumEventsNode::class,
            'datadog_aggregate_spans' => DatadogAggregateSpansNode::class,
            'datadog_api_request' => DatadogApiRequestNode::class,
            'datadog_assign_error_issue' => DatadogAssignErrorIssueNode::class,
            'datadog_assign_signal' => DatadogAssignSignalNode::class,
            'datadog_cancel_downtime' => DatadogCancelDowntimeNode::class,
            'datadog_check_monitor_deletable' => DatadogCheckMonitorDeletableNode::class,
            'datadog_check_slo_deletable' => DatadogCheckSloDeletableNode::class,
            'datadog_comment_on_case' => DatadogCommentOnCaseNode::class,
            'datadog_create_api_synthetic' => DatadogCreateApiSyntheticNode::class,
            'datadog_create_browser_synthetic' => DatadogCreateBrowserSyntheticNode::class,
            'datadog_create_case' => DatadogCreateCaseNode::class,
            'datadog_create_dashboard' => DatadogCreateDashboardNode::class,
            'datadog_create_dashboard_list' => DatadogCreateDashboardListNode::class,
            'datadog_create_downtime' => DatadogCreateDowntimeNode::class,
            'datadog_create_event' => DatadogCreateEventNode::class,
            'datadog_create_incident' => DatadogCreateIncidentNode::class,
            'datadog_create_incident_todo' => DatadogCreateIncidentTodoNode::class,
            'datadog_create_monitor' => DatadogCreateMonitorNode::class,
            'datadog_create_notebook' => DatadogCreateNotebookNode::class,
            'datadog_create_slo' => DatadogCreateSloNode::class,
            'datadog_create_slo_correction' => DatadogCreateSloCorrectionNode::class,
            'datadog_create_team' => DatadogCreateTeamNode::class,
            'datadog_create_user' => DatadogCreateUserNode::class,
            'datadog_create_webhook' => DatadogCreateWebhookNode::class,
            'datadog_delete_dashboard' => DatadogDeleteDashboardNode::class,
            'datadog_delete_dashboard_list' => DatadogDeleteDashboardListNode::class,
            'datadog_delete_host_tags' => DatadogDeleteHostTagsNode::class,
            'datadog_delete_incident' => DatadogDeleteIncidentNode::class,
            'datadog_delete_monitor' => DatadogDeleteMonitorNode::class,
            'datadog_delete_notebook' => DatadogDeleteNotebookNode::class,
            'datadog_delete_service' => DatadogDeleteServiceNode::class,
            'datadog_delete_slo' => DatadogDeleteSloNode::class,
            'datadog_delete_slo_correction' => DatadogDeleteSloCorrectionNode::class,
            'datadog_delete_synthetics' => DatadogDeleteSyntheticsNode::class,
            'datadog_delete_team' => DatadogDeleteTeamNode::class,
            'datadog_delete_webhook' => DatadogDeleteWebhookNode::class,
            'datadog_disable_user' => DatadogDisableUserNode::class,
            'datadog_get_case' => DatadogGetCaseNode::class,
            'datadog_get_current_user' => DatadogGetCurrentUserNode::class,
            'datadog_get_dashboard' => DatadogGetDashboardNode::class,
            'datadog_get_dashboard_list' => DatadogGetDashboardListNode::class,
            'datadog_get_downtime' => DatadogGetDowntimeNode::class,
            'datadog_get_error_issue' => DatadogGetErrorIssueNode::class,
            'datadog_get_event' => DatadogGetEventNode::class,
            'datadog_get_host_tags' => DatadogGetHostTagsNode::class,
            'datadog_get_host_totals' => DatadogGetHostTotalsNode::class,
            'datadog_get_incident' => DatadogGetIncidentNode::class,
            'datadog_get_metric_metadata' => DatadogGetMetricMetadataNode::class,
            'datadog_get_metric_tags' => DatadogGetMetricTagsNode::class,
            'datadog_get_monitor' => DatadogGetMonitorNode::class,
            'datadog_get_notebook' => DatadogGetNotebookNode::class,
            'datadog_get_security_signal' => DatadogGetSecuritySignalNode::class,
            'datadog_get_service' => DatadogGetServiceNode::class,
            'datadog_get_slo' => DatadogGetSloNode::class,
            'datadog_get_slo_correction' => DatadogGetSloCorrectionNode::class,
            'datadog_get_slo_history' => DatadogGetSloHistoryNode::class,
            'datadog_get_synthetic' => DatadogGetSyntheticNode::class,
            'datadog_get_synthetic_result' => DatadogGetSyntheticResultNode::class,
            'datadog_get_synthetic_uptime' => DatadogGetSyntheticUptimeNode::class,
            'datadog_get_team' => DatadogGetTeamNode::class,
            'datadog_get_trace' => DatadogGetTraceNode::class,
            'datadog_get_user' => DatadogGetUserNode::class,
            'datadog_get_webhook' => DatadogGetWebhookNode::class,
            'datadog_list_active_metrics' => DatadogListActiveMetricsNode::class,
            'datadog_list_dashboard_list_items' => DatadogListDashboardListItemsNode::class,
            'datadog_list_dashboard_lists' => DatadogListDashboardListsNode::class,
            'datadog_list_dashboards' => DatadogListDashboardsNode::class,
            'datadog_list_downtimes' => DatadogListDowntimesNode::class,
            'datadog_list_events' => DatadogListEventsNode::class,
            'datadog_list_host_tags' => DatadogListHostTagsNode::class,
            'datadog_list_hosts' => DatadogListHostsNode::class,
            'datadog_list_incident_attachments' => DatadogListIncidentAttachmentsNode::class,
            'datadog_list_incident_impacts' => DatadogListIncidentImpactsNode::class,
            'datadog_list_incident_todos' => DatadogListIncidentTodosNode::class,
            'datadog_list_incidents' => DatadogListIncidentsNode::class,
            'datadog_list_metrics' => DatadogListMetricsNode::class,
            'datadog_list_monitor_alerts' => DatadogListMonitorAlertsNode::class,
            'datadog_list_monitor_downtimes' => DatadogListMonitorDowntimesNode::class,
            'datadog_list_monitor_state_changes' => DatadogListMonitorStateChangesNode::class,
            'datadog_list_monitors' => DatadogListMonitorsNode::class,
            'datadog_list_new_incidents' => DatadogListNewIncidentsNode::class,
            'datadog_list_notebooks' => DatadogListNotebooksNode::class,
            'datadog_list_services' => DatadogListServicesNode::class,
            'datadog_list_slo_corrections' => DatadogListSloCorrectionsNode::class,
            'datadog_list_slos' => DatadogListSlosNode::class,
            'datadog_list_synthetic_locations' => DatadogListSyntheticLocationsNode::class,
            'datadog_list_synthetic_results' => DatadogListSyntheticResultsNode::class,
            'datadog_list_synthetic_variables' => DatadogListSyntheticVariablesNode::class,
            'datadog_list_synthetics' => DatadogListSyntheticsNode::class,
            'datadog_list_team_members' => DatadogListTeamMembersNode::class,
            'datadog_list_teams' => DatadogListTeamsNode::class,
            'datadog_list_users' => DatadogListUsersNode::class,
            'datadog_mute_host' => DatadogMuteHostNode::class,
            'datadog_mute_monitor' => DatadogMuteMonitorNode::class,
            'datadog_query_metrics' => DatadogQueryMetricsNode::class,
            'datadog_remove_from_dashboard_list' => DatadogRemoveFromDashboardListNode::class,
            'datadog_remove_team_member' => DatadogRemoveTeamMemberNode::class,
            'datadog_search_audit_events' => DatadogSearchAuditEventsNode::class,
            'datadog_search_cases' => DatadogSearchCasesNode::class,
            'datadog_search_error_issues' => DatadogSearchErrorIssuesNode::class,
            'datadog_search_events' => DatadogSearchEventsNode::class,
            'datadog_search_incidents' => DatadogSearchIncidentsNode::class,
            'datadog_search_logs' => DatadogSearchLogsNode::class,
            'datadog_search_monitor_groups' => DatadogSearchMonitorGroupsNode::class,
            'datadog_search_monitors' => DatadogSearchMonitorsNode::class,
            'datadog_search_rum_events' => DatadogSearchRumEventsNode::class,
            'datadog_search_security_signals' => DatadogSearchSecuritySignalsNode::class,
            'datadog_search_slos' => DatadogSearchSlosNode::class,
            'datadog_search_spans' => DatadogSearchSpansNode::class,
            'datadog_search_synthetics' => DatadogSearchSyntheticsNode::class,
            'datadog_send_logs' => DatadogSendLogsNode::class,
            'datadog_set_synthetic_status' => DatadogSetSyntheticStatusNode::class,
            'datadog_submit_distribution' => DatadogSubmitDistributionNode::class,
            'datadog_submit_metric' => DatadogSubmitMetricNode::class,
            'datadog_submit_service_check' => DatadogSubmitServiceCheckNode::class,
            'datadog_take_graph_snapshot' => DatadogTakeGraphSnapshotNode::class,
            'datadog_trigger_synthetics' => DatadogTriggerSyntheticsNode::class,
            'datadog_unmute_host' => DatadogUnmuteHostNode::class,
            'datadog_unmute_monitor' => DatadogUnmuteMonitorNode::class,
            'datadog_update_api_synthetic' => DatadogUpdateApiSyntheticNode::class,
            'datadog_update_browser_synthetic' => DatadogUpdateBrowserSyntheticNode::class,
            'datadog_update_case_status' => DatadogUpdateCaseStatusNode::class,
            'datadog_update_dashboard' => DatadogUpdateDashboardNode::class,
            'datadog_update_dashboard_list' => DatadogUpdateDashboardListNode::class,
            'datadog_update_downtime' => DatadogUpdateDowntimeNode::class,
            'datadog_update_error_issue_state' => DatadogUpdateErrorIssueStateNode::class,
            'datadog_update_host_tags' => DatadogUpdateHostTagsNode::class,
            'datadog_update_incident' => DatadogUpdateIncidentNode::class,
            'datadog_update_metric_metadata' => DatadogUpdateMetricMetadataNode::class,
            'datadog_update_monitor' => DatadogUpdateMonitorNode::class,
            'datadog_update_notebook' => DatadogUpdateNotebookNode::class,
            'datadog_update_signal_state' => DatadogUpdateSignalStateNode::class,
            'datadog_update_slo' => DatadogUpdateSloNode::class,
            'datadog_update_slo_correction' => DatadogUpdateSloCorrectionNode::class,
            'datadog_update_team' => DatadogUpdateTeamNode::class,
            'datadog_update_user' => DatadogUpdateUserNode::class,
            'datadog_update_webhook' => DatadogUpdateWebhookNode::class,
            'datadog_upsert_service' => DatadogUpsertServiceNode::class,
            'datadog_validate_keys' => DatadogValidateKeysNode::class,
            'datadog_validate_monitor' => DatadogValidateMonitorNode::class,
            'sentry_add_issue_comment' => SentryAddIssueCommentNode::class,
            'sentry_add_member' => SentryAddMemberNode::class,
            'sentry_add_member_to_team' => SentryAddMemberToTeamNode::class,
            'sentry_add_team_to_project' => SentryAddTeamToProjectNode::class,
            'sentry_api_request' => SentryApiRequestNode::class,
            'sentry_assign_issue' => SentryAssignIssueNode::class,
            'sentry_bulk_delete_issues' => SentryBulkDeleteIssuesNode::class,
            'sentry_bulk_update_issues' => SentryBulkUpdateIssuesNode::class,
            'sentry_create_alert' => SentryCreateAlertNode::class,
            'sentry_create_client_key' => SentryCreateClientKeyNode::class,
            'sentry_create_cron_monitor' => SentryCreateCronMonitorNode::class,
            'sentry_create_dashboard' => SentryCreateDashboardNode::class,
            'sentry_create_deploy' => SentryCreateDeployNode::class,
            'sentry_create_monitor' => SentryCreateMonitorNode::class,
            'sentry_create_project' => SentryCreateProjectNode::class,
            'sentry_create_release' => SentryCreateReleaseNode::class,
            'sentry_create_saved_query' => SentryCreateSavedQueryNode::class,
            'sentry_create_service_hook' => SentryCreateServiceHookNode::class,
            'sentry_create_team' => SentryCreateTeamNode::class,
            'sentry_delete_alert' => SentryDeleteAlertNode::class,
            'sentry_delete_client_key' => SentryDeleteClientKeyNode::class,
            'sentry_delete_cron_monitor' => SentryDeleteCronMonitorNode::class,
            'sentry_delete_dashboard' => SentryDeleteDashboardNode::class,
            'sentry_delete_issue' => SentryDeleteIssueNode::class,
            'sentry_delete_issue_comment' => SentryDeleteIssueCommentNode::class,
            'sentry_delete_monitor' => SentryDeleteMonitorNode::class,
            'sentry_delete_project' => SentryDeleteProjectNode::class,
            'sentry_delete_release' => SentryDeleteReleaseNode::class,
            'sentry_delete_saved_query' => SentryDeleteSavedQueryNode::class,
            'sentry_delete_service_hook' => SentryDeleteServiceHookNode::class,
            'sentry_delete_team' => SentryDeleteTeamNode::class,
            'sentry_get_alert' => SentryGetAlertNode::class,
            'sentry_get_client_key' => SentryGetClientKeyNode::class,
            'sentry_get_cron_monitor' => SentryGetCronMonitorNode::class,
            'sentry_get_dashboard' => SentryGetDashboardNode::class,
            'sentry_get_issue' => SentryGetIssueNode::class,
            'sentry_get_issue_event' => SentryGetIssueEventNode::class,
            'sentry_get_issue_tag' => SentryGetIssueTagNode::class,
            'sentry_get_member' => SentryGetMemberNode::class,
            'sentry_get_monitor' => SentryGetMonitorNode::class,
            'sentry_get_organization' => SentryGetOrganizationNode::class,
            'sentry_get_organization_stats' => SentryGetOrganizationStatsNode::class,
            'sentry_get_project' => SentryGetProjectNode::class,
            'sentry_get_project_event' => SentryGetProjectEventNode::class,
            'sentry_get_project_stats' => SentryGetProjectStatsNode::class,
            'sentry_get_release' => SentryGetReleaseNode::class,
            'sentry_get_saved_query' => SentryGetSavedQueryNode::class,
            'sentry_get_service_hook' => SentryGetServiceHookNode::class,
            'sentry_get_session_stats' => SentryGetSessionStatsNode::class,
            'sentry_get_stats_by_project' => SentryGetStatsByProjectNode::class,
            'sentry_get_team' => SentryGetTeamNode::class,
            'sentry_get_trace' => SentryGetTraceNode::class,
            'sentry_ignore_issue' => SentryIgnoreIssueNode::class,
            'sentry_list_alerts' => SentryListAlertsNode::class,
            'sentry_list_changed_issues' => SentryListChangedIssuesNode::class,
            'sentry_list_client_keys' => SentryListClientKeysNode::class,
            'sentry_list_cron_checkins' => SentryListCronCheckinsNode::class,
            'sentry_list_cron_monitors' => SentryListCronMonitorsNode::class,
            'sentry_list_dashboards' => SentryListDashboardsNode::class,
            'sentry_list_deploys' => SentryListDeploysNode::class,
            'sentry_list_environments' => SentryListEnvironmentsNode::class,
            'sentry_list_integrations' => SentryListIntegrationsNode::class,
            'sentry_list_issue_comments' => SentryListIssueCommentsNode::class,
            'sentry_list_issue_events' => SentryListIssueEventsNode::class,
            'sentry_list_issue_hashes' => SentryListIssueHashesNode::class,
            'sentry_list_issue_tag_values' => SentryListIssueTagValuesNode::class,
            'sentry_list_issues' => SentryListIssuesNode::class,
            'sentry_list_members' => SentryListMembersNode::class,
            'sentry_list_monitors' => SentryListMonitorsNode::class,
            'sentry_list_new_issues' => SentryListNewIssuesNode::class,
            'sentry_list_organizations' => SentryListOrganizationsNode::class,
            'sentry_list_project_events' => SentryListProjectEventsNode::class,
            'sentry_list_project_members' => SentryListProjectMembersNode::class,
            'sentry_list_project_teams' => SentryListProjectTeamsNode::class,
            'sentry_list_project_users' => SentryListProjectUsersNode::class,
            'sentry_list_projects' => SentryListProjectsNode::class,
            'sentry_list_regressed_issues' => SentryListRegressedIssuesNode::class,
            'sentry_list_release_commits' => SentryListReleaseCommitsNode::class,
            'sentry_list_release_files' => SentryListReleaseFilesNode::class,
            'sentry_list_releases' => SentryListReleasesNode::class,
            'sentry_list_replays' => SentryListReplaysNode::class,
            'sentry_list_repositories' => SentryListRepositoriesNode::class,
            'sentry_list_repository_commits' => SentryListRepositoryCommitsNode::class,
            'sentry_list_saved_queries' => SentryListSavedQueriesNode::class,
            'sentry_list_service_hooks' => SentryListServiceHooksNode::class,
            'sentry_list_tag_values' => SentryListTagValuesNode::class,
            'sentry_list_tags' => SentryListTagsNode::class,
            'sentry_list_team_members' => SentryListTeamMembersNode::class,
            'sentry_list_team_projects' => SentryListTeamProjectsNode::class,
            'sentry_list_teams' => SentryListTeamsNode::class,
            'sentry_list_user_feedback' => SentryListUserFeedbackNode::class,
            'sentry_query_events' => SentryQueryEventsNode::class,
            'sentry_remove_member' => SentryRemoveMemberNode::class,
            'sentry_remove_member_from_team' => SentryRemoveMemberFromTeamNode::class,
            'sentry_remove_team_from_project' => SentryRemoveTeamFromProjectNode::class,
            'sentry_resolve_event_id' => SentryResolveEventIdNode::class,
            'sentry_resolve_issue' => SentryResolveIssueNode::class,
            'sentry_resolve_short_id' => SentryResolveShortIdNode::class,
            'sentry_unresolve_issue' => SentryUnresolveIssueNode::class,
            'sentry_update_alert' => SentryUpdateAlertNode::class,
            'sentry_update_client_key' => SentryUpdateClientKeyNode::class,
            'sentry_update_cron_monitor' => SentryUpdateCronMonitorNode::class,
            'sentry_update_dashboard' => SentryUpdateDashboardNode::class,
            'sentry_update_issue' => SentryUpdateIssueNode::class,
            'sentry_update_issue_comment' => SentryUpdateIssueCommentNode::class,
            'sentry_update_member_role' => SentryUpdateMemberRoleNode::class,
            'sentry_update_monitor' => SentryUpdateMonitorNode::class,
            'sentry_update_organization' => SentryUpdateOrganizationNode::class,
            'sentry_update_project' => SentryUpdateProjectNode::class,
            'sentry_update_release' => SentryUpdateReleaseNode::class,
            'sentry_update_saved_query' => SentryUpdateSavedQueryNode::class,
            'sentry_update_service_hook' => SentryUpdateServiceHookNode::class,
            'sentry_update_team' => SentryUpdateTeamNode::class,
            'ahrefs_domain_rating' => AhrefsDomainRatingNode::class,
            'ahrefs_backlinks_stats' => AhrefsBacklinksStatsNode::class,
            'ahrefs_outlinks_stats' => AhrefsOutlinksStatsNode::class,
            'ahrefs_metrics' => AhrefsMetricsNode::class,
            'ahrefs_metrics_by_country' => AhrefsMetricsByCountryNode::class,
            'ahrefs_pages_by_traffic' => AhrefsPagesByTrafficNode::class,
            'ahrefs_ai_responses_count' => AhrefsAiResponsesCountNode::class,
            'ahrefs_domain_rating_history' => AhrefsDomainRatingHistoryNode::class,
            'ahrefs_url_rating_history' => AhrefsUrlRatingHistoryNode::class,
            'ahrefs_refdomains_history' => AhrefsRefdomainsHistoryNode::class,
            'ahrefs_pages_history' => AhrefsPagesHistoryNode::class,
            'ahrefs_metrics_history' => AhrefsMetricsHistoryNode::class,
            'ahrefs_keywords_history' => AhrefsKeywordsHistoryNode::class,
            'ahrefs_total_search_volume_history' => AhrefsTotalSearchVolumeHistoryNode::class,
            'ahrefs_backlinks' => AhrefsBacklinksNode::class,
            'ahrefs_broken_backlinks' => AhrefsBrokenBacklinksNode::class,
            'ahrefs_referring_domains' => AhrefsReferringDomainsNode::class,
            'ahrefs_anchors' => AhrefsAnchorsNode::class,
            'ahrefs_organic_keywords' => AhrefsOrganicKeywordsNode::class,
            'ahrefs_organic_competitors' => AhrefsOrganicCompetitorsNode::class,
            'ahrefs_top_pages' => AhrefsTopPagesNode::class,
            'ahrefs_paid_pages' => AhrefsPaidPagesNode::class,
            'ahrefs_best_pages_by_backlinks' => AhrefsBestPagesByBacklinksNode::class,
            'ahrefs_best_pages_by_internal_links' => AhrefsBestPagesByInternalLinksNode::class,
            'ahrefs_linked_domains' => AhrefsLinkedDomainsNode::class,
            'ahrefs_outgoing_external_anchors' => AhrefsOutgoingExternalAnchorsNode::class,
            'ahrefs_outgoing_internal_anchors' => AhrefsOutgoingInternalAnchorsNode::class,
            'ahrefs_crawled_pages' => AhrefsCrawledPagesNode::class,
            'ahrefs_batch_analysis' => AhrefsBatchAnalysisNode::class,
            'ahrefs_keywords_overview' => AhrefsKeywordsOverviewNode::class,
            'ahrefs_keyword_volume_history' => AhrefsKeywordVolumeHistoryNode::class,
            'ahrefs_keyword_volume_by_country' => AhrefsKeywordVolumeByCountryNode::class,
            'ahrefs_matching_terms' => AhrefsMatchingTermsNode::class,
            'ahrefs_related_terms' => AhrefsRelatedTermsNode::class,
            'ahrefs_search_suggestions' => AhrefsSearchSuggestionsNode::class,
            'ahrefs_serp_overview' => AhrefsSerpOverviewNode::class,
            'ahrefs_rank_tracker_overview' => AhrefsRankTrackerOverviewNode::class,
            'ahrefs_rank_tracker_competitors_overview' => AhrefsRankTrackerCompetitorsOverviewNode::class,
            'ahrefs_rank_tracker_competitors_pages' => AhrefsRankTrackerCompetitorsPagesNode::class,
            'ahrefs_rank_tracker_competitors_domains' => AhrefsRankTrackerCompetitorsDomainsNode::class,
            'ahrefs_rank_tracker_competitors_stats' => AhrefsRankTrackerCompetitorsStatsNode::class,
            'ahrefs_rank_tracker_serp_overview' => AhrefsRankTrackerSerpOverviewNode::class,
            'ahrefs_site_audit_projects' => AhrefsSiteAuditProjectsNode::class,
            'ahrefs_site_audit_issues' => AhrefsSiteAuditIssuesNode::class,
            'ahrefs_site_audit_page_explorer' => AhrefsSiteAuditPageExplorerNode::class,
            'ahrefs_site_audit_page_content' => AhrefsSiteAuditPageContentNode::class,
            'ahrefs_brand_radar_ai_responses' => AhrefsBrandRadarAiResponsesNode::class,
            'ahrefs_brand_radar_impressions_overview' => AhrefsBrandRadarImpressionsOverviewNode::class,
            'ahrefs_brand_radar_citations_overview' => AhrefsBrandRadarCitationsOverviewNode::class,
            'ahrefs_brand_radar_mentions_overview' => AhrefsBrandRadarMentionsOverviewNode::class,
            'ahrefs_brand_radar_sov_overview' => AhrefsBrandRadarSovOverviewNode::class,
            'ahrefs_brand_radar_impressions_history' => AhrefsBrandRadarImpressionsHistoryNode::class,
            'ahrefs_brand_radar_citations_history' => AhrefsBrandRadarCitationsHistoryNode::class,
            'ahrefs_brand_radar_mentions_history' => AhrefsBrandRadarMentionsHistoryNode::class,
            'ahrefs_brand_radar_sov_history' => AhrefsBrandRadarSovHistoryNode::class,
            'ahrefs_brand_radar_topics' => AhrefsBrandRadarTopicsNode::class,
            'ahrefs_brand_radar_cited_pages' => AhrefsBrandRadarCitedPagesNode::class,
            'ahrefs_brand_radar_cited_domains' => AhrefsBrandRadarCitedDomainsNode::class,
            'ahrefs_brand_radar_web_pages' => AhrefsBrandRadarWebPagesNode::class,
            'ahrefs_limits_and_usage' => AhrefsLimitsAndUsageNode::class,
            'ahrefs_crawler_ips' => AhrefsCrawlerIpsNode::class,
            'ahrefs_crawler_ip_ranges' => AhrefsCrawlerIpRangesNode::class,
            'ahrefs_domain_rating_free' => AhrefsDomainRatingFreeNode::class,
            'ahrefs_domain_rating_top_domains' => AhrefsDomainRatingTopDomainsNode::class,
            'ahrefs_list_projects' => AhrefsListProjectsNode::class,
            'ahrefs_create_projects' => AhrefsCreateProjectsNode::class,
            'ahrefs_update_project' => AhrefsUpdateProjectNode::class,
            'ahrefs_delete_projects' => AhrefsDeleteProjectsNode::class,
            'ahrefs_list_project_keywords' => AhrefsListProjectKeywordsNode::class,
            'ahrefs_add_project_keywords' => AhrefsAddProjectKeywordsNode::class,
            'ahrefs_delete_project_keywords' => AhrefsDeleteProjectKeywordsNode::class,
            'ahrefs_add_project_keyword_tags' => AhrefsAddProjectKeywordTagsNode::class,
            'ahrefs_delete_project_keyword_tags' => AhrefsDeleteProjectKeywordTagsNode::class,
            'ahrefs_list_project_competitors' => AhrefsListProjectCompetitorsNode::class,
            'ahrefs_add_project_competitors' => AhrefsAddProjectCompetitorsNode::class,
            'ahrefs_delete_project_competitors' => AhrefsDeleteProjectCompetitorsNode::class,
            'ahrefs_list_keyword_lists' => AhrefsListKeywordListsNode::class,
            'ahrefs_create_keyword_list' => AhrefsCreateKeywordListNode::class,
            'ahrefs_delete_keyword_lists' => AhrefsDeleteKeywordListsNode::class,
            'ahrefs_list_keyword_list_items' => AhrefsListKeywordListItemsNode::class,
            'ahrefs_add_keyword_list_items' => AhrefsAddKeywordListItemsNode::class,
            'ahrefs_delete_keyword_list_items' => AhrefsDeleteKeywordListItemsNode::class,
            'ahrefs_list_locations' => AhrefsListLocationsNode::class,
            'ahrefs_list_brand_radar_prompts' => AhrefsListBrandRadarPromptsNode::class,
            'ahrefs_add_brand_radar_prompts' => AhrefsAddBrandRadarPromptsNode::class,
            'ahrefs_update_brand_radar_prompts' => AhrefsUpdateBrandRadarPromptsNode::class,
            'ahrefs_delete_brand_radar_prompts' => AhrefsDeleteBrandRadarPromptsNode::class,
            'ahrefs_list_brand_radar_reports' => AhrefsListBrandRadarReportsNode::class,
            'ahrefs_create_brand_radar_report' => AhrefsCreateBrandRadarReportNode::class,
            'ahrefs_update_brand_radar_report' => AhrefsUpdateBrandRadarReportNode::class,
            'ahrefs_web_analytics_stats' => AhrefsWebAnalyticsStatsNode::class,
            'ahrefs_web_analytics_chart' => AhrefsWebAnalyticsChartNode::class,
            'ahrefs_gsc_performance_history' => AhrefsGscPerformanceHistoryNode::class,
            'ahrefs_gsc_positions_history' => AhrefsGscPositionsHistoryNode::class,
            'ahrefs_gsc_pages_history' => AhrefsGscPagesHistoryNode::class,
            'ahrefs_gsc_performance_by_device' => AhrefsGscPerformanceByDeviceNode::class,
            'ahrefs_gsc_metrics_by_country' => AhrefsGscMetricsByCountryNode::class,
            'ahrefs_gsc_ctr_by_position' => AhrefsGscCtrByPositionNode::class,
            'ahrefs_gsc_performance_by_position' => AhrefsGscPerformanceByPositionNode::class,
            'ahrefs_gsc_keyword_history' => AhrefsGscKeywordHistoryNode::class,
            'ahrefs_gsc_keywords' => AhrefsGscKeywordsNode::class,
            'ahrefs_gsc_page_history' => AhrefsGscPageHistoryNode::class,
            'ahrefs_gsc_pages' => AhrefsGscPagesNode::class,
            'ahrefs_gsc_anonymous_queries' => AhrefsGscAnonymousQueriesNode::class,
            'ahrefs_social_channels' => AhrefsSocialChannelsNode::class,
            'ahrefs_social_authors' => AhrefsSocialAuthorsNode::class,
            'ahrefs_social_posts' => AhrefsSocialPostsNode::class,
            'ahrefs_social_activity_history' => AhrefsSocialActivityHistoryNode::class,
            'ahrefs_social_channel_metrics' => AhrefsSocialChannelMetricsNode::class,
            'ahrefs_social_post_metrics' => AhrefsSocialPostMetricsNode::class,
            'ahrefs_social_create_post' => AhrefsSocialCreatePostNode::class,
            'ahrefs_social_update_post' => AhrefsSocialUpdatePostNode::class,
            'ahrefs_social_delete_post' => AhrefsSocialDeletePostNode::class,
            'ahrefs_content_topics' => AhrefsContentTopicsNode::class,
            'ahrefs_content_gap' => AhrefsContentGapNode::class,
            'ahrefs_link_intersect_domains' => AhrefsLinkIntersectDomainsNode::class,
            'ahrefs_link_intersect_pages' => AhrefsLinkIntersectPagesNode::class,
            'ahrefs_web_analytics_breakdown' => AhrefsWebAnalyticsBreakdownNode::class,
            'ahrefs_web_analytics_breakdown_chart' => AhrefsWebAnalyticsBreakdownChartNode::class,
            'ahrefs_list_new_backlinks' => AhrefsListNewBacklinksNode::class,
            'ahrefs_list_lost_backlinks' => AhrefsListLostBacklinksNode::class,
            'ahrefs_list_new_referring_domains' => AhrefsListNewReferringDomainsNode::class,
            'ahrefs_list_lost_referring_domains' => AhrefsListLostReferringDomainsNode::class,
            'ahrefs_list_new_keywords' => AhrefsListNewKeywordsNode::class,
            'ahrefs_list_lost_keywords' => AhrefsListLostKeywordsNode::class,
            'ahrefs_list_rank_changes' => AhrefsListRankChangesNode::class,
            'ahrefs_api_request' => AhrefsApiRequestNode::class,
            'excel_create_workbook' => ExcelCreateWorkbookNode::class,
            'excel_search_workbooks' => ExcelSearchWorkbooksNode::class,
            'excel_list_workbooks' => ExcelListWorkbooksNode::class,
            'excel_list_updated_workbooks' => ExcelListUpdatedWorkbooksNode::class,
            'excel_get_workbook' => ExcelGetWorkbookNode::class,
            'excel_download_workbook' => ExcelDownloadWorkbookNode::class,
            'excel_copy_workbook' => ExcelCopyWorkbookNode::class,
            'excel_rename_workbook' => ExcelRenameWorkbookNode::class,
            'excel_delete_workbook' => ExcelDeleteWorkbookNode::class,
            'excel_calculate_workbook' => ExcelCalculateWorkbookNode::class,
            'excel_list_comments' => ExcelListCommentsNode::class,
            'excel_list_drives' => ExcelListDrivesNode::class,
            'excel_search_sites' => ExcelSearchSitesNode::class,
            'excel_list_folders' => ExcelListFoldersNode::class,
            'excel_list_worksheets' => ExcelListWorksheetsNode::class,
            'excel_get_worksheet' => ExcelGetWorksheetNode::class,
            'excel_find_worksheet' => ExcelFindWorksheetNode::class,
            'excel_add_worksheet' => ExcelAddWorksheetNode::class,
            'excel_find_or_create_worksheet' => ExcelFindOrCreateWorksheetNode::class,
            'excel_update_worksheet' => ExcelUpdateWorksheetNode::class,
            'excel_delete_worksheet' => ExcelDeleteWorksheetNode::class,
            'excel_protect_worksheet' => ExcelProtectWorksheetNode::class,
            'excel_unprotect_worksheet' => ExcelUnprotectWorksheetNode::class,
            'excel_list_rows' => ExcelListRowsNode::class,
            'excel_list_updated_rows' => ExcelListUpdatedRowsNode::class,
            'excel_get_row' => ExcelGetRowNode::class,
            'excel_get_columns' => ExcelGetColumnsNode::class,
            'excel_find_row' => ExcelFindRowNode::class,
            'excel_add_row' => ExcelAddRowNode::class,
            'excel_add_rows' => ExcelAddRowsNode::class,
            'excel_update_row' => ExcelUpdateRowNode::class,
            'excel_find_or_create_row' => ExcelFindOrCreateRowNode::class,
            'excel_delete_row' => ExcelDeleteRowNode::class,
            'excel_clear_row' => ExcelClearRowNode::class,
            'excel_clear_column' => ExcelClearColumnNode::class,
            'excel_get_range' => ExcelGetRangeNode::class,
            'excel_update_range' => ExcelUpdateRangeNode::class,
            'excel_clear_range' => ExcelClearRangeNode::class,
            'excel_insert_range' => ExcelInsertRangeNode::class,
            'excel_delete_range' => ExcelDeleteRangeNode::class,
            'excel_merge_range' => ExcelMergeRangeNode::class,
            'excel_unmerge_range' => ExcelUnmergeRangeNode::class,
            'excel_sort_range' => ExcelSortRangeNode::class,
            'excel_format_range' => ExcelFormatRangeNode::class,
            'excel_get_cell' => ExcelGetCellNode::class,
            'excel_update_cell' => ExcelUpdateCellNode::class,
            'excel_watch_cell' => ExcelWatchCellNode::class,
            'excel_list_tables' => ExcelListTablesNode::class,
            'excel_get_table' => ExcelGetTableNode::class,
            'excel_add_table' => ExcelAddTableNode::class,
            'excel_update_table' => ExcelUpdateTableNode::class,
            'excel_delete_table' => ExcelDeleteTableNode::class,
            'excel_convert_table_to_range' => ExcelConvertTableToRangeNode::class,
            'excel_get_table_range' => ExcelGetTableRangeNode::class,
            'excel_list_table_rows' => ExcelListTableRowsNode::class,
            'excel_list_updated_table_rows' => ExcelListUpdatedTableRowsNode::class,
            'excel_get_table_row' => ExcelGetTableRowNode::class,
            'excel_add_table_row' => ExcelAddTableRowNode::class,
            'excel_update_table_row' => ExcelUpdateTableRowNode::class,
            'excel_delete_table_row' => ExcelDeleteTableRowNode::class,
            'excel_find_table_row' => ExcelFindTableRowNode::class,
            'excel_list_table_columns' => ExcelListTableColumnsNode::class,
            'excel_add_table_column' => ExcelAddTableColumnNode::class,
            'excel_update_table_column' => ExcelUpdateTableColumnNode::class,
            'excel_delete_table_column' => ExcelDeleteTableColumnNode::class,
            'excel_sort_table' => ExcelSortTableNode::class,
            'excel_filter_table_column' => ExcelFilterTableColumnNode::class,
            'excel_clear_table_filters' => ExcelClearTableFiltersNode::class,
            'excel_reapply_table_filters' => ExcelReapplyTableFiltersNode::class,
            'excel_list_names' => ExcelListNamesNode::class,
            'excel_get_name' => ExcelGetNameNode::class,
            'excel_get_name_range' => ExcelGetNameRangeNode::class,
            'excel_add_name' => ExcelAddNameNode::class,
            'excel_update_name' => ExcelUpdateNameNode::class,
            'excel_call_function' => ExcelCallFunctionNode::class,
            'excel_list_charts' => ExcelListChartsNode::class,
            'excel_get_chart' => ExcelGetChartNode::class,
            'excel_add_chart' => ExcelAddChartNode::class,
            'excel_update_chart' => ExcelUpdateChartNode::class,
            'excel_set_chart_data' => ExcelSetChartDataNode::class,
            'excel_get_chart_image' => ExcelGetChartImageNode::class,
            'excel_delete_chart' => ExcelDeleteChartNode::class,
            'excel_list_pivot_tables' => ExcelListPivotTablesNode::class,
            'excel_refresh_pivot_table' => ExcelRefreshPivotTableNode::class,
            'excel_api_request' => ExcelApiRequestNode::class,
            'semrush_domain_overview' => SemrushDomainOverviewNode::class,
            'semrush_domain_overview_all_databases' => SemrushDomainOverviewAllDatabasesNode::class,
            'semrush_domain_overview_history' => SemrushDomainOverviewHistoryNode::class,
            'semrush_winners_and_losers' => SemrushWinnersAndLosersNode::class,
            'semrush_semrush_rank' => SemrushSemrushRankNode::class,
            'semrush_domain_organic_keywords' => SemrushDomainOrganicKeywordsNode::class,
            'semrush_domain_paid_keywords' => SemrushDomainPaidKeywordsNode::class,
            'semrush_domain_ad_copies' => SemrushDomainAdCopiesNode::class,
            'semrush_domain_organic_competitors' => SemrushDomainOrganicCompetitorsNode::class,
            'semrush_domain_paid_competitors' => SemrushDomainPaidCompetitorsNode::class,
            'semrush_domain_ad_history' => SemrushDomainAdHistoryNode::class,
            'semrush_domain_pla_keywords' => SemrushDomainPlaKeywordsNode::class,
            'semrush_domain_pla_copies' => SemrushDomainPlaCopiesNode::class,
            'semrush_domain_pla_competitors' => SemrushDomainPlaCompetitorsNode::class,
            'semrush_domain_organic_pages' => SemrushDomainOrganicPagesNode::class,
            'semrush_domain_organic_subdomains' => SemrushDomainOrganicSubdomainsNode::class,
            'semrush_keyword_overview' => SemrushKeywordOverviewNode::class,
            'semrush_keyword_overview_all_databases' => SemrushKeywordOverviewAllDatabasesNode::class,
            'semrush_keywords_batch_overview' => SemrushKeywordsBatchOverviewNode::class,
            'semrush_keyword_organic_results' => SemrushKeywordOrganicResultsNode::class,
            'semrush_keyword_paid_results' => SemrushKeywordPaidResultsNode::class,
            'semrush_related_keywords' => SemrushRelatedKeywordsNode::class,
            'semrush_broad_match_keywords' => SemrushBroadMatchKeywordsNode::class,
            'semrush_keyword_questions' => SemrushKeywordQuestionsNode::class,
            'semrush_keyword_ads_history' => SemrushKeywordAdsHistoryNode::class,
            'semrush_keyword_difficulty' => SemrushKeywordDifficultyNode::class,
            'semrush_url_overview' => SemrushUrlOverviewNode::class,
            'semrush_url_overview_history' => SemrushUrlOverviewHistoryNode::class,
            'semrush_url_overview_all_databases' => SemrushUrlOverviewAllDatabasesNode::class,
            'semrush_url_organic_keywords' => SemrushUrlOrganicKeywordsNode::class,
            'semrush_url_paid_keywords' => SemrushUrlPaidKeywordsNode::class,
            'semrush_subdomain_overview' => SemrushSubdomainOverviewNode::class,
            'semrush_subdomain_overview_all_databases' => SemrushSubdomainOverviewAllDatabasesNode::class,
            'semrush_subdomain_overview_history' => SemrushSubdomainOverviewHistoryNode::class,
            'semrush_subdomain_organic_keywords' => SemrushSubdomainOrganicKeywordsNode::class,
            'semrush_subdomain_paid_keywords' => SemrushSubdomainPaidKeywordsNode::class,
            'semrush_subdomain_organic_pages' => SemrushSubdomainOrganicPagesNode::class,
            'semrush_subdomain_ad_copies' => SemrushSubdomainAdCopiesNode::class,
            'semrush_subfolder_overview' => SemrushSubfolderOverviewNode::class,
            'semrush_subfolder_overview_all_databases' => SemrushSubfolderOverviewAllDatabasesNode::class,
            'semrush_subfolder_overview_history' => SemrushSubfolderOverviewHistoryNode::class,
            'semrush_subfolder_organic_keywords' => SemrushSubfolderOrganicKeywordsNode::class,
            'semrush_subfolder_paid_keywords' => SemrushSubfolderPaidKeywordsNode::class,
            'semrush_subfolder_organic_pages' => SemrushSubfolderOrganicPagesNode::class,
            'semrush_subfolder_ad_copies' => SemrushSubfolderAdCopiesNode::class,
            'semrush_backlinks_overview' => SemrushBacklinksOverviewNode::class,
            'semrush_backlinks' => SemrushBacklinksNode::class,
            'semrush_referring_domains' => SemrushReferringDomainsNode::class,
            'semrush_referring_ips' => SemrushReferringIpsNode::class,
            'semrush_backlinks_tld_distribution' => SemrushBacklinksTldDistributionNode::class,
            'semrush_referring_domains_by_country' => SemrushReferringDomainsByCountryNode::class,
            'semrush_backlink_anchors' => SemrushBacklinkAnchorsNode::class,
            'semrush_indexed_pages' => SemrushIndexedPagesNode::class,
            'semrush_backlink_competitors' => SemrushBacklinkCompetitorsNode::class,
            'semrush_backlinks_comparison_by_domain' => SemrushBacklinksComparisonByDomainNode::class,
            'semrush_backlinks_batch_comparison' => SemrushBacklinksBatchComparisonNode::class,
            'semrush_authority_score_profile' => SemrushAuthorityScoreProfileNode::class,
            'semrush_referring_categories_profile' => SemrushReferringCategoriesProfileNode::class,
            'semrush_domain_categories' => SemrushDomainCategoriesNode::class,
            'semrush_backlinks_history' => SemrushBacklinksHistoryNode::class,
            'semrush_compare_domains' => SemrushCompareDomainsNode::class,
            'semrush_traffic_summary' => SemrushTrafficSummaryNode::class,
            'semrush_daily_traffic' => SemrushDailyTrafficNode::class,
            'semrush_weekly_traffic' => SemrushWeeklyTrafficNode::class,
            'semrush_purchase_conversion' => SemrushPurchaseConversionNode::class,
            'semrush_industry_categories' => SemrushIndustryCategoriesNode::class,
            'semrush_traffic_sources' => SemrushTrafficSourcesNode::class,
            'semrush_traffic_destinations' => SemrushTrafficDestinationsNode::class,
            'semrush_geo_distribution' => SemrushGeoDistributionNode::class,
            'semrush_trends_subdomains' => SemrushTrendsSubdomainsNode::class,
            'semrush_top_pages' => SemrushTopPagesNode::class,
            'semrush_traffic_rank' => SemrushTrafficRankNode::class,
            'semrush_audience_insights' => SemrushAudienceInsightsNode::class,
            'semrush_data_accuracy' => SemrushDataAccuracyNode::class,
            'semrush_trends_subfolders' => SemrushTrendsSubfoldersNode::class,
            'semrush_age_and_sex_distribution' => SemrushAgeAndSexDistributionNode::class,
            'semrush_household_distribution' => SemrushHouseholdDistributionNode::class,
            'semrush_income_distribution' => SemrushIncomeDistributionNode::class,
            'semrush_education_distribution' => SemrushEducationDistributionNode::class,
            'semrush_occupation_distribution' => SemrushOccupationDistributionNode::class,
            'semrush_audience_interests' => SemrushAudienceInterestsNode::class,
            'semrush_social_media' => SemrushSocialMediaNode::class,
            'semrush_get_api_units' => SemrushGetApiUnitsNode::class,
            'semrush_get_trends_limits' => SemrushGetTrendsLimitsNode::class,
            'semrush_list_projects' => SemrushListProjectsNode::class,
            'semrush_get_project' => SemrushGetProjectNode::class,
            'semrush_create_project' => SemrushCreateProjectNode::class,
            'semrush_update_project' => SemrushUpdateProjectNode::class,
            'semrush_delete_project' => SemrushDeleteProjectNode::class,
            'semrush_create_tracking_campaign' => SemrushCreateTrackingCampaignNode::class,
            'semrush_enable_tracking_emails' => SemrushEnableTrackingEmailsNode::class,
            'semrush_disable_tracking_emails' => SemrushDisableTrackingEmailsNode::class,
            'semrush_add_tracking_keywords' => SemrushAddTrackingKeywordsNode::class,
            'semrush_remove_tracking_keywords' => SemrushRemoveTrackingKeywordsNode::class,
            'semrush_add_tracking_tags' => SemrushAddTrackingTagsNode::class,
            'semrush_remove_tracking_tags' => SemrushRemoveTrackingTagsNode::class,
            'semrush_list_tracking_competitors' => SemrushListTrackingCompetitorsNode::class,
            'semrush_add_tracking_competitors' => SemrushAddTrackingCompetitorsNode::class,
            'semrush_remove_tracking_competitors' => SemrushRemoveTrackingCompetitorsNode::class,
            'semrush_list_tracking_campaigns' => SemrushListTrackingCampaignsNode::class,
            'semrush_search_tracking_locations' => SemrushSearchTrackingLocationsNode::class,
            'semrush_tracking_campaign_dates' => SemrushTrackingCampaignDatesNode::class,
            'semrush_tracking_organic_overview' => SemrushTrackingOrganicOverviewNode::class,
            'semrush_tracking_adwords_overview' => SemrushTrackingAdwordsOverviewNode::class,
            'semrush_tracking_organic_positions' => SemrushTrackingOrganicPositionsNode::class,
            'semrush_tracking_adwords_positions' => SemrushTrackingAdwordsPositionsNode::class,
            'semrush_tracking_organic_competitors' => SemrushTrackingOrganicCompetitorsNode::class,
            'semrush_tracking_adwords_competitors' => SemrushTrackingAdwordsCompetitorsNode::class,
            'semrush_tracking_organic_visibility' => SemrushTrackingOrganicVisibilityNode::class,
            'semrush_tracking_adwords_visibility' => SemrushTrackingAdwordsVisibilityNode::class,
            'semrush_tracking_organic_landing_pages' => SemrushTrackingOrganicLandingPagesNode::class,
            'semrush_tracking_adwords_landing_pages' => SemrushTrackingAdwordsLandingPagesNode::class,
            'semrush_enable_site_audit' => SemrushEnableSiteAuditNode::class,
            'semrush_update_site_audit' => SemrushUpdateSiteAuditNode::class,
            'semrush_create_site_audit_campaign' => SemrushCreateSiteAuditCampaignNode::class,
            'semrush_run_site_audit' => SemrushRunSiteAuditNode::class,
            'semrush_get_site_audit' => SemrushGetSiteAuditNode::class,
            'semrush_list_site_audit_snapshots' => SemrushListSiteAuditSnapshotsNode::class,
            'semrush_get_site_audit_snapshot' => SemrushGetSiteAuditSnapshotNode::class,
            'semrush_list_site_audit_issue_types' => SemrushListSiteAuditIssueTypesNode::class,
            'semrush_get_site_audit_issue' => SemrushGetSiteAuditIssueNode::class,
            'semrush_find_site_audit_page' => SemrushFindSiteAuditPageNode::class,
            'semrush_get_site_audit_page' => SemrushGetSiteAuditPageNode::class,
            'semrush_get_site_audit_history' => SemrushGetSiteAuditHistoryNode::class,
            'semrush_list_new_backlinks' => SemrushListNewBacklinksNode::class,
            'semrush_list_lost_backlinks' => SemrushListLostBacklinksNode::class,
            'semrush_list_new_referring_domains' => SemrushListNewReferringDomainsNode::class,
            'semrush_list_new_keywords' => SemrushListNewKeywordsNode::class,
            'semrush_list_lost_keywords' => SemrushListLostKeywordsNode::class,
            'semrush_api_request' => SemrushApiRequestNode::class,
        ];
    }
}
