<?php

namespace Database\Seeders;

use App\Models\Templates\PageTemplate;
use App\Services\Workflows\Pages\PageSchema;
use Illuminate\Database\Seeder;

/**
 * Global layouts offered when creating a page: a few plain starters, and
 * full pages for common uses with a cover, introduction, the form, the
 * result and the sections around them. Field and output blocks are left
 * unbound: `PageSchema::fromTemplate()` fills them with the chosen
 * workflow's inputs and outputs, adding or removing blocks to match.
 */
class PageTemplateSeeder extends Seeder
{
    public function run(PageSchema $schema): void
    {
        foreach ([...$this->templates(), ...$this->fullTemplates()] as $slug => $template) {
            PageTemplate::query()->updateOrCreate(
                ['slug' => $slug, 'workspace_id' => null],
                [...$template, 'content' => $schema->normalize($template['content']), 'visibility' => 'public'],
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            'simple-form' => [
                'name' => 'Simple form',
                'description' => 'A title, the form and the result underneath. Clean and quick to fill in.',
                'category' => 'Forms',
                'icon' => 'form',
                'color' => '#4F46E5',
                'content' => [
                    'theme' => ['accent' => '#4f46e5', 'background' => 'soft', 'radius' => 'lg', 'width' => 'medium'],
                    'blocks' => [
                        self::block('heading', ['text' => 'Title', 'level' => 1]),
                        self::block('text', ['markdown' => '']),
                        self::block('field', []),
                        self::block('submit', ['label' => 'Run', 'align' => 'stretch']),
                        self::block('divider', []),
                        self::block('status', []),
                        self::block('output', []),
                    ],
                ],
            ],
            'landing' => [
                'name' => 'Landing page',
                'description' => 'A centered introduction with a short how-it-works note before the form, for sharing with people outside the team.',
                'category' => 'Public pages',
                'icon' => 'rocket',
                'color' => '#7C3AED',
                'content' => [
                    'theme' => ['accent' => '#7c3aed', 'background' => 'gradient', 'radius' => 'xl', 'width' => 'medium'],
                    'blocks' => [
                        self::block('heading', ['text' => 'Title', 'level' => 1, 'align' => 'center']),
                        self::block('text', ['markdown' => '', 'align' => 'center']),
                        self::block('spacer', ['size' => 'sm']),
                        self::block('callout', [
                            'tone' => 'info',
                            'title' => 'How it works',
                            'markdown' => 'Fill in the form below and press the button. Your result appears on this page in a few moments.',
                        ]),
                        self::block('field', []),
                        self::block('submit', ['label' => 'Get started', 'running_label' => 'Working on it…', 'align' => 'center']),
                        self::block('spacer', ['size' => 'sm']),
                        self::block('status', []),
                        self::block('output', []),
                    ],
                ],
            ],
            'request-form' => [
                'name' => 'Request form',
                'description' => 'Inputs side by side in two columns and a confirmation note: good for intake, leads and requests.',
                'category' => 'Forms',
                'icon' => 'inbox',
                'color' => '#F59E0B',
                'content' => [
                    'theme' => ['accent' => '#d97706', 'background' => 'dots', 'radius' => 'md', 'width' => 'medium', 'font' => 'serif'],
                    'blocks' => [
                        self::block('heading', ['text' => 'Title', 'level' => 1]),
                        self::block('text', ['markdown' => '']),
                        self::block('field', [], 'half'),
                        self::block('field', [], 'half'),
                        self::block('submit', ['label' => 'Send request', 'running_label' => 'Sending…', 'align' => 'stretch']),
                        self::block('status', ['idle_text' => 'We’ll show the outcome here as soon as your request is processed.']),
                        self::block('output', []),
                    ],
                ],
            ],
            'report' => [
                'name' => 'Report generator',
                'description' => 'A wide layout that renders the result as a formatted document, for summaries, briefs and reports.',
                'category' => 'Content',
                'icon' => 'file-text',
                'color' => '#0EA5E9',
                'content' => [
                    'theme' => ['accent' => '#0284c7', 'background' => 'plain', 'radius' => 'md', 'width' => 'wide'],
                    'blocks' => [
                        self::block('heading', ['text' => 'Title', 'level' => 1]),
                        self::block('text', ['markdown' => '']),
                        self::block('field', [], 'third'),
                        self::block('submit', ['label' => 'Generate report', 'running_label' => 'Writing…', 'align' => 'left']),
                        self::block('divider', ['style' => 'dashed']),
                        self::block('status', []),
                        self::block('output', ['display' => 'markdown', 'empty_text' => 'Your report will appear here.']),
                    ],
                ],
            ],
            'developer-tool' => [
                'name' => 'Developer tool',
                'description' => 'Dark, monospaced and compact, with copyable raw output, for internal tools and API-style workflows.',
                'category' => 'Internal tools',
                'icon' => 'terminal',
                'color' => '#10A37F',
                'content' => [
                    'theme' => ['accent' => '#10b981', 'appearance' => 'dark', 'background' => 'plain', 'radius' => 'sm', 'font' => 'mono', 'width' => 'narrow', 'show_branding' => false],
                    'blocks' => [
                        self::block('heading', ['text' => 'Title', 'level' => 1]),
                        self::block('field', []),
                        self::block('submit', ['label' => 'Run', 'running_label' => 'Running…', 'align' => 'left']),
                        self::block('status', []),
                        self::block('output', ['display' => 'json', 'copyable' => true]),
                    ],
                ],
            ],
        ];
    }

    /**
     * Complete pages for common uses. The first heading becomes the page's
     * title when one is created, so it reads as a sample title here.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fullTemplates(): array
    {
        return [
            'content-studio' => [
                'name' => 'AI Content Studio',
                'description' => 'A full writing tool page: hero, three selling points, the brief form, a formatted draft and a how-it-works and FAQ section underneath.',
                'category' => 'Content',
                'icon' => 'pen',
                'color' => '#7C3AED',
                'content' => [
                    'theme' => ['accent' => '#7c3aed', 'background' => 'gradient', 'radius' => 'xl', 'width' => 'wide', 'cover_url' => self::photo('1499750310107-5fef28a66643')],
                    'blocks' => [
                        self::heading('AI Content Studio', 1, 'center'),
                        self::text('Turn a rough idea into a polished, on-brand draft in under a minute: blog posts, newsletters, product copy and social posts.', 'center'),
                        self::block('spacer', ['size' => 'sm']),
                        self::callout('success', 'On brand', 'Writes in the tone you choose and keeps your terminology consistent.', 'third'),
                        self::callout('info', 'Ready to publish', 'Headings, short paragraphs and a clear call to action, formatted in Markdown.', 'third'),
                        self::callout('warning', 'Seconds, not hours', 'Get a complete first draft fast, then spend your time on the final polish.', 'third'),
                        self::block('spacer', ['size' => 'md']),
                        self::heading('Write your brief', 2),
                        self::text('The more specific you are about the topic, audience and goal, the better the draft.'),
                        self::field(),
                        self::field(),
                        self::submit('Write my draft', 'Writing your draft…', 'stretch'),
                        self::status('Your draft will appear below as soon as it’s ready.'),
                        self::output('Your draft', 'markdown', 'Fill in the brief and press the button to get your draft.'),
                        self::block('divider', ['style' => 'solid']),
                        self::heading('How it works', 2),
                        self::text(<<<'MD'
                            1. **Describe what you need**: the topic, who it’s for and what you want readers to do.
                            2. **We draft it**: structure, headline and body copy, written for your audience.
                            3. **Copy and refine**: use the copy button, paste it into your editor and make it yours.
                            MD),
                        self::heading('Frequently asked questions', 2),
                        self::text(<<<'MD'
                            ### Can I use the draft as-is?
                            It’s ready to publish, but a quick human pass to add your own examples always makes it better.

                            ### Is my brief stored?
                            Your brief is only used to write this draft.

                            ### What formats can it write?
                            Blog posts, newsletters, landing page copy, product descriptions and social posts.
                            MD),
                        self::callout('info', 'Tip', 'Paste an example of writing you like into the brief and ask for the same style.'),
                    ],
                ],
            ],
            'lead-qualification' => [
                'name' => 'Lead Capture & Qualification',
                'description' => 'A sales page that collects a prospect’s details, scores the fit and recommends the next step, with social proof and FAQs.',
                'category' => 'Sales',
                'icon' => 'target',
                'color' => '#2563EB',
                'content' => [
                    'theme' => ['accent' => '#2563eb', 'background' => 'soft', 'radius' => 'lg', 'width' => 'medium', 'cover_url' => self::photo('1556761175-5973dc0f32e7')],
                    'blocks' => [
                        self::heading('Get your free growth assessment', 1),
                        self::text('Tell us a little about your business and we’ll show you how well we fit (and the best next step for you) instantly.'),
                        self::callout('success', 'Free · 2 minutes · No commitment', 'You’ll see your result on this page right away.'),
                        self::field('half'),
                        self::field('half'),
                        self::submit('Get my assessment', 'Assessing…', 'stretch'),
                        self::status('Your assessment will appear here.'),
                        self::output('Your fit score', 'auto', 'Your score appears here.', 'half'),
                        self::output('Recommended next step', 'markdown', 'Your next step appears here.', 'half'),
                        self::block('spacer', ['size' => 'sm']),
                        self::block('divider', ['style' => 'solid']),
                        self::heading('Why teams choose us', 2),
                        self::callout('info', 'Fast setup', 'Most teams are live within a week.', 'third'),
                        self::callout('info', 'Dedicated support', 'A real person who knows your account.', 'third'),
                        self::callout('info', 'Proven results', 'Customers see measurable impact in the first quarter.', 'third'),
                        self::text(<<<'MD'
                            > “The assessment told us exactly where to start. We booked a call the same day.”
                            >
                            > - **Operations lead**, growing SaaS company
                            MD),
                        self::heading('Questions', 2),
                        self::text(<<<'MD'
                            ### What happens after I submit?
                            You get your score and a recommended next step immediately. If it’s a good fit, our team may reach out.

                            ### Will you share my details?
                            No. Your details are only used to prepare your assessment and follow up with you.
                            MD),
                    ],
                ],
            ],
            'support-assistant' => [
                'name' => 'Customer Support Assistant',
                'description' => 'A help-center page: quick links, a question form, a formatted answer and an escalation note for when people are still stuck.',
                'category' => 'Support',
                'icon' => 'headphones',
                'color' => '#0D9488',
                'content' => [
                    'theme' => ['accent' => '#0d9488', 'background' => 'plain', 'radius' => 'md', 'width' => 'medium', 'cover_url' => self::photo('1521737604893-d14cc237f11d')],
                    'blocks' => [
                        self::heading('How can we help?', 1),
                        self::text('Ask anything about your account, billing or the product and get an answer right away.'),
                        self::callout('info', 'Popular topics', <<<'MD'
                            - Resetting your password or changing your email
                            - Updating billing details and downloading invoices
                            - Inviting teammates and managing roles
                            MD),
                        self::heading('Ask a question', 2),
                        self::field(),
                        self::field(),
                        self::submit('Get an answer', 'Finding the answer…', 'left'),
                        self::status('Your answer will show up here.'),
                        self::output('Answer', 'markdown', 'Ask a question to see the answer.'),
                        self::block('spacer', ['size' => 'sm']),
                        self::callout('warning', 'Still stuck?', 'If the answer doesn’t solve it, reply to any of our emails or contact support: a person will pick it up.'),
                        self::block('divider', ['style' => 'dashed']),
                        self::heading('Before you ask', 3),
                        self::text(<<<'MD'
                            - Include any error message exactly as you see it.
                            - Say what you were trying to do and what happened instead.
                            - Never share passwords or full card numbers.
                            MD),
                    ],
                ],
            ],
            'document-summarizer' => [
                'name' => 'Document Summarizer',
                'description' => 'Paste a long document and get a summary and action items side by side, with feature cards and usage tips.',
                'category' => 'Productivity',
                'icon' => 'file-text',
                'color' => '#4F46E5',
                'content' => [
                    'theme' => ['accent' => '#4f46e5', 'background' => 'plain', 'radius' => 'md', 'width' => 'wide', 'cover_url' => self::photo('1454165804606-c3d57bc86b40')],
                    'blocks' => [
                        self::heading('Document Summarizer', 1),
                        self::text('Reports, contracts, meeting notes or research: get the gist and what to do next without reading every page.'),
                        self::callout('info', 'The short version', 'A clear summary in a few paragraphs.', 'third'),
                        self::callout('success', 'What to do next', 'Decisions, owners and action items pulled out for you.', 'third'),
                        self::callout('warning', 'Nothing invented', 'Only what the document actually says.', 'third'),
                        self::block('spacer', ['size' => 'sm']),
                        self::field(),
                        self::submit('Summarize', 'Reading the document…', 'left'),
                        self::status('Paste your document and press Summarize.'),
                        self::output('Summary', 'markdown', 'The summary appears here.', 'half'),
                        self::output('Action items', 'markdown', 'Action items appear here.', 'half'),
                        self::block('divider', ['style' => 'solid']),
                        self::heading('Tips for better summaries', 2),
                        self::text(<<<'MD'
                            - Paste the full text rather than a link, so nothing is missed.
                            - Remove headers, footers and page numbers if you can.
                            - For very long documents, summarize one section at a time.
                            MD),
                    ],
                ],
            ],
            'data-extractor' => [
                'name' => 'Data Extractor',
                'description' => 'Turns messy text, emails or invoices into a clean table plus the raw structured result, with a privacy note and supported formats.',
                'category' => 'Operations',
                'icon' => 'table',
                'color' => '#EA580C',
                'content' => [
                    'theme' => ['accent' => '#ea580c', 'background' => 'dots', 'radius' => 'md', 'width' => 'wide', 'cover_url' => self::photo('1551288049-bebda4e38f71')],
                    'blocks' => [
                        self::heading('Data Extractor', 1),
                        self::text('Paste an invoice, an email thread or any messy text and get clean, structured data back: ready for a spreadsheet or your CRM.'),
                        self::callout('warning', 'Handle with care', 'Remove passwords, full card numbers and other secrets before pasting.'),
                        self::field(),
                        self::field(),
                        self::submit('Extract data', 'Extracting…', 'stretch'),
                        self::status('Results will appear below.'),
                        self::output('Extracted data', 'table', 'Your table appears here.'),
                        self::output('Raw result', 'json', 'The structured result appears here.'),
                        self::block('divider', ['style' => 'dashed']),
                        self::heading('Works well with', 2),
                        self::callout('info', 'Invoices & receipts', 'Vendor, dates, line items and totals.', 'third'),
                        self::callout('info', 'Emails & tickets', 'Names, companies, requests and deadlines.', 'third'),
                        self::callout('info', 'Forms & notes', 'Any fields you’d otherwise copy by hand.', 'third'),
                    ],
                ],
            ],
            'event-registration' => [
                'name' => 'Event Registration',
                'description' => 'An event landing page with the key details, agenda, venue photo, a sign-up form, a confirmation and FAQs.',
                'category' => 'Events',
                'icon' => 'calendar',
                'color' => '#DB2777',
                'content' => [
                    'theme' => ['accent' => '#db2777', 'background' => 'gradient', 'radius' => 'xl', 'width' => 'medium', 'cover_url' => self::photo('1540575467063-178a50c2df87')],
                    'blocks' => [
                        self::heading('Product Summit 2026', 1, 'center'),
                        self::text('A day of talks, workshops and conversations with the people building what’s next. Seats are limited.', 'center'),
                        self::callout('info', 'When', "**Thursday, 12 November**  \n9:00 – 17:00", 'third'),
                        self::callout('info', 'Where', "**City Conference Centre**  \nand online", 'third'),
                        self::callout('success', 'Price', '**Free** for registered guests', 'third'),
                        self::heading('Reserve your spot', 2),
                        self::field('half'),
                        self::field('half'),
                        self::submit('Register now', 'Reserving your seat…', 'stretch'),
                        self::status('You’ll see your confirmation here.'),
                        self::output('Your registration', 'markdown', 'Your confirmation appears here.'),
                        self::block('divider', ['style' => 'solid']),
                        self::heading('Agenda', 2),
                        self::text(<<<'MD'
                            - **9:00**: Registration and coffee
                            - **10:00**: Opening keynote
                            - **11:30**: Breakout workshops
                            - **13:00**: Lunch and networking
                            - **14:30**: Panel: what’s next
                            - **16:30**: Closing remarks
                            MD),
                        self::image(self::photo('1505373877841-8d25f7d46678'), 'The conference venue', 'md'),
                        self::heading('FAQ', 2),
                        self::text(<<<'MD'
                            ### Can I attend online?
                            Yes: every talk is streamed. Choose online when you register.

                            ### Can I bring a colleague?
                            Each person should register separately so we can save them a seat.
                            MD),
                    ],
                ],
            ],
            'feedback-survey' => [
                'name' => 'Feedback Survey',
                'description' => 'A friendly, focused survey page with a time estimate, privacy note, the questions and a personal thank-you.',
                'category' => 'Research',
                'icon' => 'message-square',
                'color' => '#16A34A',
                'content' => [
                    'theme' => ['accent' => '#16a34a', 'background' => 'soft', 'radius' => 'lg', 'width' => 'narrow', 'cover_url' => self::photo('1531482615713-2afd69097998')],
                    'blocks' => [
                        self::heading('Tell us how we’re doing', 1),
                        self::text('Your feedback decides what we build next. It takes about two minutes.'),
                        self::callout('success', 'Anonymous and short', 'Answer only what you want: every question is read by the team.'),
                        self::field(),
                        self::field(),
                        self::field(),
                        self::submit('Send feedback', 'Sending…', 'stretch'),
                        self::status('Thank you for taking the time.'),
                        self::output('A note from the team', 'markdown', 'Your thank-you appears here.'),
                        self::block('spacer', ['size' => 'sm']),
                        self::text('*We review every response weekly and share what we changed in our updates.*', 'center'),
                    ],
                ],
            ],
            'image-generator' => [
                'name' => 'Image Generator',
                'description' => 'A dark creative studio page with prompt tips, the prompt form and a large image result.',
                'category' => 'Creative',
                'icon' => 'image',
                'color' => '#A855F7',
                'content' => [
                    'theme' => ['accent' => '#a855f7', 'appearance' => 'dark', 'background' => 'gradient', 'radius' => 'xl', 'width' => 'medium', 'cover_url' => self::photo('1557683316-973673baf926')],
                    'blocks' => [
                        self::heading('Image Studio', 1),
                        self::text('Describe what you imagine and get an original image in seconds.'),
                        self::callout('info', 'Be specific', 'Subject, setting and mood.', 'third'),
                        self::callout('info', 'Pick a style', 'Photo, illustration, 3D, watercolor…', 'third'),
                        self::callout('info', 'Set the light', 'Golden hour, studio, neon.', 'third'),
                        self::field(),
                        self::field(),
                        self::submit('Generate image', 'Creating your image…', 'stretch'),
                        self::status('Your image will appear below.'),
                        self::output('Your image', 'image', 'Your image appears here.'),
                        self::block('divider', ['style' => 'dashed']),
                        self::heading('Prompt ideas', 2),
                        self::text(<<<'MD'
                            - *A cozy reading nook by a rainy window, warm lamp light, watercolor*
                            - *A futuristic city market at night, neon signs, cinematic photo*
                            - *A friendly robot watering plants, soft 3D render, pastel colors*
                            MD),
                    ],
                ],
            ],
            'website-audit' => [
                'name' => 'SEO & Website Audit',
                'description' => 'An audit tool page: enter a site, see the score and top fixes side by side, then the full report, with what’s checked and FAQs.',
                'category' => 'Marketing',
                'icon' => 'search',
                'color' => '#0284C7',
                'content' => [
                    'theme' => ['accent' => '#0284c7', 'background' => 'plain', 'radius' => 'md', 'width' => 'wide', 'cover_url' => self::photo('1460925895917-afdab827c52f')],
                    'blocks' => [
                        self::heading('Free website audit', 1),
                        self::text('Find out what’s holding your site back in search, and exactly what to fix first.'),
                        self::field('two_thirds'),
                        self::submit('Run audit', 'Auditing your site…', 'stretch'),
                        self::status('Your report will appear below.'),
                        self::output('Overall score', 'auto', 'Your score appears here.', 'third'),
                        self::output('Top fixes', 'markdown', 'Your most important fixes appear here.', 'two_thirds'),
                        self::output('Full report', 'markdown', 'The full report appears here.'),
                        self::block('divider', ['style' => 'solid']),
                        self::heading('What we check', 2),
                        self::callout('info', 'Speed', 'Load time, image sizes and blocking scripts.', 'third'),
                        self::callout('info', 'On-page SEO', 'Titles, descriptions, headings and links.', 'third'),
                        self::callout('info', 'Content', 'Readability, keywords and thin pages.', 'third'),
                        self::heading('FAQ', 2),
                        self::text(<<<'MD'
                            ### How long does it take?
                            Usually under a minute, depending on the size of the site.

                            ### Do I need to install anything?
                            No, just enter the address of a public page.
                            MD),
                    ],
                ],
            ],
            'internal-request' => [
                'name' => 'Internal Request Desk',
                'description' => 'A no-nonsense internal tool for requests and approvals: guidelines, the request form, its status and a ticket reference.',
                'category' => 'Internal tools',
                'icon' => 'briefcase',
                'color' => '#334155',
                'content' => [
                    'theme' => ['accent' => '#334155', 'background' => 'plain', 'radius' => 'sm', 'width' => 'medium', 'cover_url' => self::photo('1497366216548-37526070297c'), 'show_branding' => false],
                    'blocks' => [
                        self::heading('Request desk', 1),
                        self::text('Submit purchase, access and IT requests in one place. Most requests are handled within one business day.'),
                        self::callout('warning', 'Internal use only', 'Don’t share this page outside the company.'),
                        self::field('half'),
                        self::field('half'),
                        self::field(),
                        self::submit('Submit request', 'Submitting…', 'left'),
                        self::status('Your request status will show here.'),
                        self::output('Status', 'markdown', 'The status of your request appears here.', 'two_thirds'),
                        self::output('Reference', 'auto', 'Your reference appears here.', 'third'),
                        self::block('divider', ['style' => 'solid']),
                        self::heading('What happens next', 2),
                        self::text(<<<'MD'
                            1. Your request is routed to the right approver.
                            2. You’ll get a message when it’s approved or if more information is needed.
                            3. Once approved, the team completes it and closes the ticket.
                            MD),
                    ],
                ],
            ],
            'translation-hub' => [
                'name' => 'Translation Hub',
                'description' => 'A translation tool page with supported uses, the text form, a formatted translation and notes on tone and terminology.',
                'category' => 'Productivity',
                'icon' => 'languages',
                'color' => '#E11D48',
                'content' => [
                    'theme' => ['accent' => '#e11d48', 'background' => 'soft', 'radius' => 'lg', 'width' => 'medium', 'cover_url' => self::photo('1523240795612-9a054b0db644')],
                    'blocks' => [
                        self::heading('Translation Hub', 1, 'center'),
                        self::text('Natural, accurate translations that keep your tone, formatting and product terms intact.', 'center'),
                        self::callout('info', 'Keeps formatting', 'Lists, headings and links stay in place.', 'half'),
                        self::callout('success', 'Sounds native', 'Idiomatic phrasing, not word-for-word.', 'half'),
                        self::field(),
                        self::field('half'),
                        self::field('half'),
                        self::submit('Translate', 'Translating…', 'stretch'),
                        self::status('Your translation will appear here.'),
                        self::output('Translation', 'markdown', 'Your translation appears here.'),
                        self::block('divider', ['style' => 'dashed']),
                        self::heading('Good to know', 3),
                        self::text(<<<'MD'
                            - Brand and product names are left untranslated.
                            - For legal or medical text, have a qualified reviewer check the result.
                            MD),
                    ],
                ],
            ],
        ];
    }

    private static function photo(string $id): string
    {
        return "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=1600&q=80";
    }

    /**
     * @return array<string, mixed>
     */
    private static function heading(string $text, int $level, string $align = 'left'): array
    {
        return self::block('heading', ['text' => $text, 'level' => $level, 'align' => $align]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function text(string $markdown, string $align = 'left'): array
    {
        return self::block('text', ['markdown' => $markdown, 'align' => $align]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function callout(string $tone, string $title, string $markdown, string $width = 'full'): array
    {
        return self::block('callout', ['tone' => $tone, 'title' => $title, 'markdown' => $markdown], $width);
    }

    /**
     * @return array<string, mixed>
     */
    private static function image(string $url, string $alt, string $height): array
    {
        return self::block('image', ['url' => $url, 'alt' => $alt, 'fit' => 'cover', 'height' => $height]);
    }

    /**
     * An unbound field slot, labelled by the workflow input it gets.
     *
     * @return array<string, mixed>
     */
    private static function field(string $width = 'full'): array
    {
        return self::block('field', [], $width);
    }

    /**
     * @return array<string, mixed>
     */
    private static function submit(string $label, string $runningLabel, string $align): array
    {
        return self::block('submit', ['label' => $label, 'running_label' => $runningLabel, 'align' => $align]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function status(string $idleText): array
    {
        return self::block('status', ['show_progress' => true, 'idle_text' => $idleText]);
    }

    /**
     * An unbound output slot with the look the template gives its result.
     *
     * @return array<string, mixed>
     */
    private static function output(string $label, string $display, string $emptyText, string $width = 'full'): array
    {
        return self::block('output', ['label' => $label, 'display' => $display, 'empty_text' => $emptyText, 'copyable' => true], $width);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private static function block(string $type, array $props, string $width = 'full'): array
    {
        return ['id' => PageSchema::newBlockId(), 'type' => $type, 'width' => $width, 'props' => $props];
    }
}
