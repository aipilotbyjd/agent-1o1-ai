<?php

use App\Services\Setup\PageBlocks;

/**
 * Global layouts offered when creating a page: a few plain starters, and
 * full pages for common uses with a cover, introduction, the form, the
 * result and the sections around them. Field and output blocks are left
 * unbound: `PageSchema::fromTemplate()` fills them with the chosen
 * workflow's inputs and outputs, adding or removing blocks to match.
 */
$starters = [
    'simple-form' => [
        'name' => 'Simple form',
        'description' => 'A title, the form and the result underneath. Clean and quick to fill in.',
        'category' => 'Forms',
        'icon' => 'form',
        'color' => '#4F46E5',
        'content' => [
            'theme' => ['accent' => '#4f46e5', 'background' => 'soft', 'radius' => 'lg', 'width' => 'medium'],
            'blocks' => [
                PageBlocks::block('heading', ['text' => 'Title', 'level' => 1]),
                PageBlocks::block('text', ['markdown' => '']),
                PageBlocks::block('field', []),
                PageBlocks::block('submit', ['label' => 'Run', 'align' => 'stretch']),
                PageBlocks::block('divider', []),
                PageBlocks::block('status', []),
                PageBlocks::block('output', []),
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
                PageBlocks::block('heading', ['text' => 'Title', 'level' => 1, 'align' => 'center']),
                PageBlocks::block('text', ['markdown' => '', 'align' => 'center']),
                PageBlocks::block('spacer', ['size' => 'sm']),
                PageBlocks::block('callout', [
                    'tone' => 'info',
                    'title' => 'How it works',
                    'markdown' => 'Fill in the form below and press the button. Your result appears on this page in a few moments.',
                ]),
                PageBlocks::block('field', []),
                PageBlocks::block('submit', ['label' => 'Get started', 'running_label' => 'Working on it…', 'align' => 'center']),
                PageBlocks::block('spacer', ['size' => 'sm']),
                PageBlocks::block('status', []),
                PageBlocks::block('output', []),
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
                PageBlocks::block('heading', ['text' => 'Title', 'level' => 1]),
                PageBlocks::block('text', ['markdown' => '']),
                PageBlocks::block('field', [], 'half'),
                PageBlocks::block('field', [], 'half'),
                PageBlocks::block('submit', ['label' => 'Send request', 'running_label' => 'Sending…', 'align' => 'stretch']),
                PageBlocks::block('status', ['idle_text' => 'We’ll show the outcome here as soon as your request is processed.']),
                PageBlocks::block('output', []),
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
                PageBlocks::block('heading', ['text' => 'Title', 'level' => 1]),
                PageBlocks::block('text', ['markdown' => '']),
                PageBlocks::block('field', [], 'third'),
                PageBlocks::block('submit', ['label' => 'Generate report', 'running_label' => 'Writing…', 'align' => 'left']),
                PageBlocks::block('divider', ['style' => 'dashed']),
                PageBlocks::block('status', []),
                PageBlocks::block('output', ['display' => 'markdown', 'empty_text' => 'Your report will appear here.']),
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
                PageBlocks::block('heading', ['text' => 'Title', 'level' => 1]),
                PageBlocks::block('field', []),
                PageBlocks::block('submit', ['label' => 'Run', 'running_label' => 'Running…', 'align' => 'left']),
                PageBlocks::block('status', []),
                PageBlocks::block('output', ['display' => 'json', 'copyable' => true]),
            ],
        ],
    ],
];

$full = [
    'content-studio' => [
        'name' => 'AI Content Studio',
        'description' => 'A full writing tool page: hero, three selling points, the brief form, a formatted draft and a how-it-works and FAQ section underneath.',
        'category' => 'Content',
        'icon' => 'pen',
        'color' => '#7C3AED',
        'content' => [
            'theme' => ['accent' => '#7c3aed', 'background' => 'gradient', 'radius' => 'xl', 'width' => 'wide', 'cover_url' => PageBlocks::photo('1499750310107-5fef28a66643')],
            'blocks' => [
                PageBlocks::heading('AI Content Studio', 1, 'center'),
                PageBlocks::text('Turn a rough idea into a polished, on-brand draft in under a minute: blog posts, newsletters, product copy and social posts.', 'center'),
                PageBlocks::block('spacer', ['size' => 'sm']),
                PageBlocks::callout('success', 'On brand', 'Writes in the tone you choose and keeps your terminology consistent.', 'third'),
                PageBlocks::callout('info', 'Ready to publish', 'Headings, short paragraphs and a clear call to action, formatted in Markdown.', 'third'),
                PageBlocks::callout('warning', 'Seconds, not hours', 'Get a complete first draft fast, then spend your time on the final polish.', 'third'),
                PageBlocks::block('spacer', ['size' => 'md']),
                PageBlocks::heading('Write your brief', 2),
                PageBlocks::text('The more specific you are about the topic, audience and goal, the better the draft.'),
                PageBlocks::field(),
                PageBlocks::field(),
                PageBlocks::submit('Write my draft', 'Writing your draft…', 'stretch'),
                PageBlocks::status('Your draft will appear below as soon as it’s ready.'),
                PageBlocks::output('Your draft', 'markdown', 'Fill in the brief and press the button to get your draft.'),
                PageBlocks::block('divider', ['style' => 'solid']),
                PageBlocks::heading('How it works', 2),
                PageBlocks::text(<<<'MD'
                        1. **Describe what you need**: the topic, who it’s for and what you want readers to do.
                        2. **We draft it**: structure, headline and body copy, written for your audience.
                        3. **Copy and refine**: use the copy button, paste it into your editor and make it yours.
                        MD),
                PageBlocks::heading('Frequently asked questions', 2),
                PageBlocks::text(<<<'MD'
                        ### Can I use the draft as-is?
                        It’s ready to publish, but a quick human pass to add your own examples always makes it better.

                        ### Is my brief stored?
                        Your brief is only used to write this draft.

                        ### What formats can it write?
                        Blog posts, newsletters, landing page copy, product descriptions and social posts.
                        MD),
                PageBlocks::callout('info', 'Tip', 'Paste an example of writing you like into the brief and ask for the same style.'),
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
            'theme' => ['accent' => '#2563eb', 'background' => 'soft', 'radius' => 'lg', 'width' => 'medium', 'cover_url' => PageBlocks::photo('1556761175-5973dc0f32e7')],
            'blocks' => [
                PageBlocks::heading('Get your free growth assessment', 1),
                PageBlocks::text('Tell us a little about your business and we’ll show you how well we fit (and the best next step for you) instantly.'),
                PageBlocks::callout('success', 'Free · 2 minutes · No commitment', 'You’ll see your result on this page right away.'),
                PageBlocks::field('half'),
                PageBlocks::field('half'),
                PageBlocks::submit('Get my assessment', 'Assessing…', 'stretch'),
                PageBlocks::status('Your assessment will appear here.'),
                PageBlocks::output('Your fit score', 'auto', 'Your score appears here.', 'half'),
                PageBlocks::output('Recommended next step', 'markdown', 'Your next step appears here.', 'half'),
                PageBlocks::block('spacer', ['size' => 'sm']),
                PageBlocks::block('divider', ['style' => 'solid']),
                PageBlocks::heading('Why teams choose us', 2),
                PageBlocks::callout('info', 'Fast setup', 'Most teams are live within a week.', 'third'),
                PageBlocks::callout('info', 'Dedicated support', 'A real person who knows your account.', 'third'),
                PageBlocks::callout('info', 'Proven results', 'Customers see measurable impact in the first quarter.', 'third'),
                PageBlocks::text(<<<'MD'
                        > “The assessment told us exactly where to start. We booked a call the same day.”
                        >
                        > - **Operations lead**, growing SaaS company
                        MD),
                PageBlocks::heading('Questions', 2),
                PageBlocks::text(<<<'MD'
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
            'theme' => ['accent' => '#0d9488', 'background' => 'plain', 'radius' => 'md', 'width' => 'medium', 'cover_url' => PageBlocks::photo('1521737604893-d14cc237f11d')],
            'blocks' => [
                PageBlocks::heading('How can we help?', 1),
                PageBlocks::text('Ask anything about your account, billing or the product and get an answer right away.'),
                PageBlocks::callout('info', 'Popular topics', <<<'MD'
                        - Resetting your password or changing your email
                        - Updating billing details and downloading invoices
                        - Inviting teammates and managing roles
                        MD),
                PageBlocks::heading('Ask a question', 2),
                PageBlocks::field(),
                PageBlocks::field(),
                PageBlocks::submit('Get an answer', 'Finding the answer…', 'left'),
                PageBlocks::status('Your answer will show up here.'),
                PageBlocks::output('Answer', 'markdown', 'Ask a question to see the answer.'),
                PageBlocks::block('spacer', ['size' => 'sm']),
                PageBlocks::callout('warning', 'Still stuck?', 'If the answer doesn’t solve it, reply to any of our emails or contact support: a person will pick it up.'),
                PageBlocks::block('divider', ['style' => 'dashed']),
                PageBlocks::heading('Before you ask', 3),
                PageBlocks::text(<<<'MD'
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
            'theme' => ['accent' => '#4f46e5', 'background' => 'plain', 'radius' => 'md', 'width' => 'wide', 'cover_url' => PageBlocks::photo('1454165804606-c3d57bc86b40')],
            'blocks' => [
                PageBlocks::heading('Document Summarizer', 1),
                PageBlocks::text('Reports, contracts, meeting notes or research: get the gist and what to do next without reading every page.'),
                PageBlocks::callout('info', 'The short version', 'A clear summary in a few paragraphs.', 'third'),
                PageBlocks::callout('success', 'What to do next', 'Decisions, owners and action items pulled out for you.', 'third'),
                PageBlocks::callout('warning', 'Nothing invented', 'Only what the document actually says.', 'third'),
                PageBlocks::block('spacer', ['size' => 'sm']),
                PageBlocks::field(),
                PageBlocks::submit('Summarize', 'Reading the document…', 'left'),
                PageBlocks::status('Paste your document and press Summarize.'),
                PageBlocks::output('Summary', 'markdown', 'The summary appears here.', 'half'),
                PageBlocks::output('Action items', 'markdown', 'Action items appear here.', 'half'),
                PageBlocks::block('divider', ['style' => 'solid']),
                PageBlocks::heading('Tips for better summaries', 2),
                PageBlocks::text(<<<'MD'
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
            'theme' => ['accent' => '#ea580c', 'background' => 'dots', 'radius' => 'md', 'width' => 'wide', 'cover_url' => PageBlocks::photo('1551288049-bebda4e38f71')],
            'blocks' => [
                PageBlocks::heading('Data Extractor', 1),
                PageBlocks::text('Paste an invoice, an email thread or any messy text and get clean, structured data back: ready for a spreadsheet or your CRM.'),
                PageBlocks::callout('warning', 'Handle with care', 'Remove passwords, full card numbers and other secrets before pasting.'),
                PageBlocks::field(),
                PageBlocks::field(),
                PageBlocks::submit('Extract data', 'Extracting…', 'stretch'),
                PageBlocks::status('Results will appear below.'),
                PageBlocks::output('Extracted data', 'table', 'Your table appears here.'),
                PageBlocks::output('Raw result', 'json', 'The structured result appears here.'),
                PageBlocks::block('divider', ['style' => 'dashed']),
                PageBlocks::heading('Works well with', 2),
                PageBlocks::callout('info', 'Invoices & receipts', 'Vendor, dates, line items and totals.', 'third'),
                PageBlocks::callout('info', 'Emails & tickets', 'Names, companies, requests and deadlines.', 'third'),
                PageBlocks::callout('info', 'Forms & notes', 'Any fields you’d otherwise copy by hand.', 'third'),
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
            'theme' => ['accent' => '#db2777', 'background' => 'gradient', 'radius' => 'xl', 'width' => 'medium', 'cover_url' => PageBlocks::photo('1540575467063-178a50c2df87')],
            'blocks' => [
                PageBlocks::heading('Product Summit 2026', 1, 'center'),
                PageBlocks::text('A day of talks, workshops and conversations with the people building what’s next. Seats are limited.', 'center'),
                PageBlocks::callout('info', 'When', "**Thursday, 12 November**  \n9:00 – 17:00", 'third'),
                PageBlocks::callout('info', 'Where', "**City Conference Centre**  \nand online", 'third'),
                PageBlocks::callout('success', 'Price', '**Free** for registered guests', 'third'),
                PageBlocks::heading('Reserve your spot', 2),
                PageBlocks::field('half'),
                PageBlocks::field('half'),
                PageBlocks::submit('Register now', 'Reserving your seat…', 'stretch'),
                PageBlocks::status('You’ll see your confirmation here.'),
                PageBlocks::output('Your registration', 'markdown', 'Your confirmation appears here.'),
                PageBlocks::block('divider', ['style' => 'solid']),
                PageBlocks::heading('Agenda', 2),
                PageBlocks::text(<<<'MD'
                        - **9:00**: Registration and coffee
                        - **10:00**: Opening keynote
                        - **11:30**: Breakout workshops
                        - **13:00**: Lunch and networking
                        - **14:30**: Panel: what’s next
                        - **16:30**: Closing remarks
                        MD),
                PageBlocks::image(PageBlocks::photo('1505373877841-8d25f7d46678'), 'The conference venue', 'md'),
                PageBlocks::heading('FAQ', 2),
                PageBlocks::text(<<<'MD'
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
            'theme' => ['accent' => '#16a34a', 'background' => 'soft', 'radius' => 'lg', 'width' => 'narrow', 'cover_url' => PageBlocks::photo('1531482615713-2afd69097998')],
            'blocks' => [
                PageBlocks::heading('Tell us how we’re doing', 1),
                PageBlocks::text('Your feedback decides what we build next. It takes about two minutes.'),
                PageBlocks::callout('success', 'Anonymous and short', 'Answer only what you want: every question is read by the team.'),
                PageBlocks::field(),
                PageBlocks::field(),
                PageBlocks::field(),
                PageBlocks::submit('Send feedback', 'Sending…', 'stretch'),
                PageBlocks::status('Thank you for taking the time.'),
                PageBlocks::output('A note from the team', 'markdown', 'Your thank-you appears here.'),
                PageBlocks::block('spacer', ['size' => 'sm']),
                PageBlocks::text('*We review every response weekly and share what we changed in our updates.*', 'center'),
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
            'theme' => ['accent' => '#a855f7', 'appearance' => 'dark', 'background' => 'gradient', 'radius' => 'xl', 'width' => 'medium', 'cover_url' => PageBlocks::photo('1557683316-973673baf926')],
            'blocks' => [
                PageBlocks::heading('Image Studio', 1),
                PageBlocks::text('Describe what you imagine and get an original image in seconds.'),
                PageBlocks::callout('info', 'Be specific', 'Subject, setting and mood.', 'third'),
                PageBlocks::callout('info', 'Pick a style', 'Photo, illustration, 3D, watercolor…', 'third'),
                PageBlocks::callout('info', 'Set the light', 'Golden hour, studio, neon.', 'third'),
                PageBlocks::field(),
                PageBlocks::field(),
                PageBlocks::submit('Generate image', 'Creating your image…', 'stretch'),
                PageBlocks::status('Your image will appear below.'),
                PageBlocks::output('Your image', 'image', 'Your image appears here.'),
                PageBlocks::block('divider', ['style' => 'dashed']),
                PageBlocks::heading('Prompt ideas', 2),
                PageBlocks::text(<<<'MD'
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
            'theme' => ['accent' => '#0284c7', 'background' => 'plain', 'radius' => 'md', 'width' => 'wide', 'cover_url' => PageBlocks::photo('1460925895917-afdab827c52f')],
            'blocks' => [
                PageBlocks::heading('Free website audit', 1),
                PageBlocks::text('Find out what’s holding your site back in search, and exactly what to fix first.'),
                PageBlocks::field('two_thirds'),
                PageBlocks::submit('Run audit', 'Auditing your site…', 'stretch'),
                PageBlocks::status('Your report will appear below.'),
                PageBlocks::output('Overall score', 'auto', 'Your score appears here.', 'third'),
                PageBlocks::output('Top fixes', 'markdown', 'Your most important fixes appear here.', 'two_thirds'),
                PageBlocks::output('Full report', 'markdown', 'The full report appears here.'),
                PageBlocks::block('divider', ['style' => 'solid']),
                PageBlocks::heading('What we check', 2),
                PageBlocks::callout('info', 'Speed', 'Load time, image sizes and blocking scripts.', 'third'),
                PageBlocks::callout('info', 'On-page SEO', 'Titles, descriptions, headings and links.', 'third'),
                PageBlocks::callout('info', 'Content', 'Readability, keywords and thin pages.', 'third'),
                PageBlocks::heading('FAQ', 2),
                PageBlocks::text(<<<'MD'
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
            'theme' => ['accent' => '#334155', 'background' => 'plain', 'radius' => 'sm', 'width' => 'medium', 'cover_url' => PageBlocks::photo('1497366216548-37526070297c'), 'show_branding' => false],
            'blocks' => [
                PageBlocks::heading('Request desk', 1),
                PageBlocks::text('Submit purchase, access and IT requests in one place. Most requests are handled within one business day.'),
                PageBlocks::callout('warning', 'Internal use only', 'Don’t share this page outside the company.'),
                PageBlocks::field('half'),
                PageBlocks::field('half'),
                PageBlocks::field(),
                PageBlocks::submit('Submit request', 'Submitting…', 'left'),
                PageBlocks::status('Your request status will show here.'),
                PageBlocks::output('Status', 'markdown', 'The status of your request appears here.', 'two_thirds'),
                PageBlocks::output('Reference', 'auto', 'Your reference appears here.', 'third'),
                PageBlocks::block('divider', ['style' => 'solid']),
                PageBlocks::heading('What happens next', 2),
                PageBlocks::text(<<<'MD'
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
            'theme' => ['accent' => '#e11d48', 'background' => 'soft', 'radius' => 'lg', 'width' => 'medium', 'cover_url' => PageBlocks::photo('1523240795612-9a054b0db644')],
            'blocks' => [
                PageBlocks::heading('Translation Hub', 1, 'center'),
                PageBlocks::text('Natural, accurate translations that keep your tone, formatting and product terms intact.', 'center'),
                PageBlocks::callout('info', 'Keeps formatting', 'Lists, headings and links stay in place.', 'half'),
                PageBlocks::callout('success', 'Sounds native', 'Idiomatic phrasing, not word-for-word.', 'half'),
                PageBlocks::field(),
                PageBlocks::field('half'),
                PageBlocks::field('half'),
                PageBlocks::submit('Translate', 'Translating…', 'stretch'),
                PageBlocks::status('Your translation will appear here.'),
                PageBlocks::output('Translation', 'markdown', 'Your translation appears here.'),
                PageBlocks::block('divider', ['style' => 'dashed']),
                PageBlocks::heading('Good to know', 3),
                PageBlocks::text(<<<'MD'
                        - Brand and product names are left untranslated.
                        - For legal or medical text, have a qualified reviewer check the result.
                        MD),
            ],
        ],
    ],
];

return [...$starters, ...$full];
