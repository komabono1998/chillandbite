<?php
declare(strict_types=1);

/**
 * Editable website content.
 *
 * Every page has sections, every section has fields. The defaults below ARE the live text, so the site is
 * unchanged until an editor saves something in Admin > Pages. Overrides are stored in `site_content`
 * under keys like "home.hero.headline". Empty/unset overrides fall back to the default.
 *
 * Field types: text | textarea | lines (one item per line) | image
 */
function site_pages(): array
{
    static $pages = null;
    if ($pages !== null) return $pages;
    $f = fn(string $key, string $label, string $type, string $default, string $help = '') =>
        ['key' => $key, 'label' => $label, 'type' => $type, 'default' => $default, 'help' => $help];
    $photo = fn() => $f('photo', 'Header background photo', 'image', '', 'Optional. Leave empty to use the built-in event photo.');

    $pages = [
        'home' => [
            'label' => 'Home page', 'icon' => '🏠', 'url' => '', 'roles' => ['editor'],
            'desc' => 'The first page visitors see: hero, next event, host, food, comedy, news, partners and the final call to action.',
            'related' => [['Events', 'admin/events', 'events'], ['News', 'admin/news', 'news'], ['Gallery', 'admin/gallery', 'gallery'], ['Sponsors', 'admin/advertising', 'advertising']],
            'sections' => [
                'hero' => ['label' => 'Hero banner', 'fields' => [
                    $f('eyebrow', 'Small label above the headline', 'text', 'Gulu City · Every first Sunday'),
                    $f('headline', 'Headline', 'text', 'Good Food. Great People.'),
                    $f('accent', 'Headline (gold second line)', 'text', 'Unforgettable Connections.'),
                    $f('intro', 'Introduction', 'textarea', "Welcome to Chill & Bite MEATINGS, Gulu City's monthly lifestyle and corporate networking experience. Join MC Kash Owakabi and Owakabi & The Geng for an exciting blend of food, music, entertainment, outdoor games, and meaningful connections."),
                    $f('btn_reserve', 'Main button', 'text', 'Reserve your spot'),
                    $f('btn_events', 'Second button', 'text', 'Explore upcoming events'),
                ]],
                'panel' => ['label' => 'Event information panel', 'fields' => [
                    $f('label', 'Panel title', 'text', 'Next experience'),
                    $f('event', 'Event name', 'text', 'Chill & Bite MEATINGS'),
                    $f('venue', 'Venue', 'text', 'Revelation Cottages, Gulu City'),
                    $f('schedule', 'Schedule', 'text', 'Every first Sunday of the month'),
                    $f('time', 'Starting time', 'text', '12:00 PM'),
                    $f('host', 'Host', 'text', 'MC Kash Owakabi'),
                ]],
                'events' => ['label' => 'Upcoming events', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Upcoming'),
                    $f('heading', 'Heading', 'text', 'Chill & Bite MEATINGS: The Monthly Experience'),
                    $f('note', 'Reservation note', 'text', 'Reservation information: individual, couple, group and corporate bookings available.'),
                ]],
                'food' => ['label' => 'Food section', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Good food'),
                    $f('heading', 'Heading', 'text', 'Fire. Smoke. Flavour.'),
                    $f('intro', 'Introduction', 'textarea', 'Sizzling skewers, slow-roasted chicken, sticky ribs and wings, served all afternoon.'),
                ]],
                'host' => ['label' => 'Meet the host', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Meet the host'),
                    $f('heading', 'Heading', 'text', 'The Man Behind the Vibes: MC Kash Owakabi'),
                    $f('bio', 'Biography (first paragraph)', 'textarea', setting('host_bio', 'From electrifying crowds to creating memorable experiences, MC Kash Owakabi brings charisma, humor, and exceptional audience engagement to every event.')),
                    $f('bio2', 'Second paragraph', 'textarea', 'As the host of Chill & Bite MEATINGS and the personality behind Laugh with Owakabi, he connects people through entertainment, conversation, and shared experiences.'),
                    $f('points', 'Highlights (one per line)', 'lines', "Charismatic host and master of ceremonies\nStand-up comedy and live audience engagement\nFounder of Owakabi & The Geng"),
                    $f('button', 'Button', 'text', 'Explore Laugh with Owakabi'),
                    $f('tag', 'Photo label', 'text', 'Your host · MC Kash Owakabi'),
                    $f('photo', 'Host portrait', 'image', 'img/host-mc-kash-owakabi.jpg', 'Portrait photo, best at 3:4 (for example 600 x 800).'),
                ]],
                'comedy' => ['label' => 'Comedy section', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Comedy'),
                    $f('heading', 'Heading', 'text', 'Laugh with Owakabi: Life Is Better with a Little Humour!'),
                    $f('shows_title', 'Shows list title', 'text', 'Upcoming comedy shows'),
                    $f('blurb', 'Short text', 'textarea', 'Guest comedians, behind-the-scenes clips and show announcements. Want MC Kash Owakabi at your event?'),
                    $f('btn_book', 'Button 1', 'text', 'Book a performance'),
                    $f('btn_more', 'Button 2', 'text', 'More comedy'),
                ]],
                'news' => ['label' => 'News section', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'News & Gossips'),
                    $f('heading', 'Heading', 'text', "Northern Buzz: What's Happening Up North?"),
                    $f('button', 'Button', 'text', 'All stories'),
                ]],
                'partners' => ['label' => 'Brand partners', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Brand partners'),
                    $f('heading', 'Heading', 'text', 'Your Brand Deserves to Be Seen.'),
                    $f('intro', 'Introduction', 'textarea', 'Connect your business with professionals, entrepreneurs, creatives, and entertainment lovers through Chill & Bite MEATINGS and our digital media platforms.'),
                    $f('btn_proposal', 'Button 1', 'text', 'Request an advertising proposal'),
                    $f('btn_partner', 'Button 2', 'text', 'Become a partner'),
                ]],
                'gallery' => ['label' => 'Gallery preview', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Gallery'),
                    $f('heading', 'Heading', 'text', 'The Atmosphere'),
                    $f('intro', 'Intro line', 'text', 'Faces, food, music and good vibes from every Chill & Bite.'),
                    $f('button', 'Button', 'text', 'See the full gallery'),
                ]],
                'cta' => ['label' => 'Final call to action', 'fields' => [
                    $f('heading', 'Heading', 'text', 'Your Next Great Connection Starts Here!'),
                    $f('tagline', 'Tagline', 'text', 'One Sunday. One Location. Endless Connections.'),
                    $f('text', 'Text', 'text', 'Join us at Revelation Cottages, Gulu City, for the next Chill & Bite MEATINGS experience.'),
                    $f('button', 'Button', 'text', 'Book your place today'),
                    $f('photo', 'Background photo', 'image', 'img/photos/photo-53.jpg', 'A bright, wide photo works best. Text sits on a frosted panel so the picture stays visible.'),
                ]],
            ],
        ],
        'about' => [
            'label' => 'About us', 'icon' => 'ℹ️', 'url' => 'about', 'roles' => ['editor'], 'related' => [],
            'desc' => 'The story of Chill & Bite MEATINGS and Owakabi & The Geng.',
            'sections' => [
                'hero' => ['label' => 'Page header', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'About us'),
                    $f('heading', 'Heading', 'text', 'Where connections are made'),
                    $f('lead', 'Subtitle', 'text', setting('tagline')),
                    $photo(),
                ]],
                'story' => ['label' => 'Our story', 'fields' => [
                    $f('heading', 'Heading', 'text', 'The story of Chill & Bite MEATINGS'),
                    $f('p1', 'Paragraph 1', 'textarea', 'Chill & Bite MEATINGS is a premium lifestyle and entertainment experience held every first Sunday of the month at Revelation Cottages, Gulu City. It brings together professionals, entrepreneurs, creatives, businesses and entertainment lovers for food, music, comedy, outdoor games and real conversations.'),
                    $f('p2', 'Paragraph 2', 'textarea', 'It is hosted by MC Kash Owakabi and organized by Owakabi & The Geng, a team passionate about building opportunities for Northern Uganda through shared experiences.'),
                ]],
                'expect' => ['label' => 'What to expect', 'fields' => [
                    $f('heading', 'Heading', 'text', 'What to expect'),
                    $f('list', 'List (one per line)', 'lines', "Great food and drinks in a relaxed outdoor setting\nNetworking with people across industries\nLive music, comedy and entertainment\nOutdoor games and social experiences\nOpportunities for brands to connect with a lively audience"),
                ]],
                'team' => ['label' => 'The team', 'fields' => [
                    $f('heading', 'Heading', 'text', 'Owakabi & The Geng'),
                    $f('text', 'Text', 'textarea', 'The creative team behind Chill & Bite MEATINGS, Laugh with Owakabi, and the Northern Buzz news and gossip magazine.'),
                    $f('button', 'Button', 'text', 'See upcoming events'),
                ]],
            ],
        ],
        'events' => [
            'label' => 'Events calendar', 'icon' => '📅', 'url' => 'events', 'roles' => ['editor', 'event_manager'],
            'desc' => 'The calendar and list of events. Individual events are managed under Events.',
            'related' => [['Manage events', 'admin/events', 'events'], ['Reservations', 'admin/reservations', 'reservations']],
            'sections' => [
                'hero' => ['label' => 'Page header', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Events calendar'),
                    $f('heading', 'Heading', 'text', "What's on"),
                    $f('lead', 'Subtitle', 'text', 'Chill & Bite MEATINGS every first Sunday, plus comedy nights, networking and special events.'),
                    $photo(),
                ]],
            ],
        ],
        'laugh' => [
            'label' => 'Laugh with Owakabi', 'icon' => '😂', 'url' => 'laugh', 'roles' => ['editor'],
            'desc' => 'The comedy page: videos, shows and the performance booking form.',
            'related' => [['Comedy videos', 'admin/comedy', 'comedy'], ['Comedy events', 'admin/events', 'events'], ['Performance requests', 'admin/inquiries?type=performance', 'inquiries']],
            'sections' => [
                'hero' => ['label' => 'Page header', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Laugh with Owakabi'),
                    $f('heading', 'Heading', 'text', 'Life is better with a little humour!'),
                    $f('lead', 'Subtitle', 'text', 'Comedy shows, video highlights and performance bookings with MC Kash Owakabi.'),
                    $f('btn_shows', 'Button 1', 'text', 'Upcoming shows'),
                    $f('btn_book', 'Button 2', 'text', 'Book a performance'),
                    $photo(),
                ]],
                'videos' => ['label' => 'Video library', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Video library'),
                    $f('heading', 'Heading', 'text', 'Watch & laugh'),
                    $f('empty', 'Message when there are no videos', 'text', 'Videos are coming soon.'),
                ]],
                'shows' => ['label' => 'Upcoming performances', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Comedy events'),
                    $f('heading', 'Heading', 'text', 'Upcoming performances'),
                    $f('empty', 'Message when there are no shows', 'text', 'No shows are scheduled right now. Check back soon or book a private performance below.'),
                ]],
                'booking' => ['label' => 'Performance booking form', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Performance booking'),
                    $f('heading', 'Heading', 'text', 'Book MC Kash Owakabi'),
                    $f('intro', 'Introduction', 'text', 'Organizations, brands, event organizers and individuals: tell us about your event.'),
                    $f('button', 'Submit button', 'text', 'Send request'),
                ]],
            ],
        ],
        'news' => [
            'label' => 'News & Gossips', 'icon' => '📰', 'url' => 'news', 'roles' => ['editor'],
            'desc' => 'The Northern Buzz magazine page. Articles are managed under News.',
            'related' => [['Manage articles', 'admin/news', 'news']],
            'sections' => [
                'hero' => ['label' => 'Page header', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'News & Gossips'),
                    $f('heading', 'Heading', 'text', "Northern Buzz: What's happening up north?"),
                    $f('lead', 'Subtitle', 'text', 'Entertainment, music, lifestyle, business and community stories from Northern Uganda.'),
                    $photo(),
                ]],
                'misc' => ['label' => 'Notes', 'fields' => [
                    $f('empty', 'Message when there are no stories', 'text', 'No stories here yet.'),
                ]],
            ],
        ],
        'advertise' => [
            'label' => 'Brands & advertising', 'icon' => '📣', 'url' => 'advertise', 'roles' => ['editor', 'marketing'],
            'desc' => 'Advertising and sponsorship opportunities, partner logos and the proposal form.',
            'related' => [['Sponsors & ads', 'admin/advertising', 'advertising'], ['Advertising enquiries', 'admin/inquiries?type=advertising', 'inquiries']],
            'sections' => [
                'hero' => ['label' => 'Page header', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Brands & advertising'),
                    $f('heading', 'Heading', 'text', 'Your brand deserves to be seen.'),
                    $f('lead', 'Subtitle', 'textarea', 'Connect your business with professionals, entrepreneurs, creatives, and entertainment lovers through Chill & Bite MEATINGS and our digital media platforms.'),
                    $f('btn_proposal', 'Button 1', 'text', 'Request a proposal'),
                    $f('btn_partner', 'Button 2', 'text', 'Become a partner'),
                    $photo(),
                ]],
                'partners' => ['label' => 'Partner logos', 'fields' => [
                    $f('heading', 'Heading', 'text', 'Our partners'),
                ]],
                'opportunities' => ['label' => 'Ways to partner', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Opportunities'),
                    $f('heading', 'Heading', 'text', 'Ways to partner with us'),
                    $f('cards', 'Cards (one per line: Title | description)', 'lines', "Event Sponsorship | Sponsor a monthly event or a specific activity.\nWebsite Banner Advertising | Advertise on selected website pages.\nSponsored Articles | Clearly labelled promotional articles.\nSocial Media Promotion | Campaigns across our social channels.\nBrand Activations | Engage attendees with approved activities.\nProduct Showcases | Put products and services in front of guests.\nComedy Show Sponsorship | Sponsor Laugh with Owakabi performances.\nAnnual Partnerships | Longer-term promotional partnerships."),
                    $f('note', 'Note under the cards', 'text', 'All paid promotions are clearly identified as sponsored content.'),
                ]],
                'proposal' => ['label' => 'Proposal form', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Advertising inquiry'),
                    $f('heading', 'Heading', 'text', 'Request a proposal'),
                    $f('button', 'Submit button', 'text', 'Send inquiry'),
                ]],
            ],
        ],
        'partner' => [
            'label' => 'Become a partner', 'icon' => '🤝', 'url' => 'partner', 'roles' => ['editor', 'marketing'],
            'desc' => 'Public partnership application page.',
            'related' => [['Partner applications', 'admin/partners', 'advertising'], ['Sponsors', 'admin/advertising', 'advertising']],
            'sections' => [
                'hero' => ['label' => 'Page header', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Partner with us'),
                    $f('heading', 'Heading', 'text', 'Grow your brand with Chill & Bite'),
                    $f('lead', 'Subtitle', 'textarea', "Join the companies putting their name in front of Gulu City's professionals, entrepreneurs, creatives and entertainment lovers, every month."),
                    $f('btn_apply', 'Button 1', 'text', 'Apply to partner'),
                    $f('btn_partners', 'Button 2', 'text', 'See our partners'),
                    $photo(),
                ]],
                'why' => ['label' => 'Benefits', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Why partner'),
                    $f('heading', 'Heading', 'text', 'What your brand gets'),
                    $f('cards', 'Benefit cards (one per line: emoji | Title | description)', 'lines', "📣 | Visibility | Your logo on our website, event materials and social media.\n🤝 | Networking | Meet decision-makers, customers and other brands at every event.\n🎯 | Market your offers | Promote your products, services and events to an engaged crowd.\n🏆 | Credibility | Be seen alongside Gulu City's most talked-about monthly experience."),
                    $f('steps', 'How it works (one per line: Title | description)', 'lines', "Apply | Send your details and company logo using the form below.\nWe review | Our partnerships team reviews every application and contacts you.\nGo live | Once approved, your logo appears under Our Partners on the website."),
                ]],
                'partners' => ['label' => 'Partner logos', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Our partners'),
                    $f('heading', 'Heading', 'text', 'Brands that grow with us'),
                    $f('empty', 'Message when there are no partners yet', 'text', 'Be the first partner to be featured here.'),
                ]],
                'apply' => ['label' => 'Application form', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Partnership application'),
                    $f('heading', 'Heading', 'text', 'Apply to become a partner'),
                    $f('intro', 'Introduction', 'text', 'Fields marked * are required. Approved partners are featured with their logo and a link to their website.'),
                    $f('button', 'Submit button', 'text', 'Submit application'),
                ]],
            ],
        ],
        'track' => [
            'label' => 'Track a booking', 'icon' => '🔎', 'url' => 'track', 'roles' => ['editor', 'event_manager'],
            'desc' => 'Where guests enter their booking reference to see their status and download their ticket.',
            'related' => [['Tickets', 'admin/tickets', 'tickets'], ['Reservations', 'admin/reservations', 'reservations']],
            'sections' => [
                'hero' => ['label' => 'Page header', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Track your booking'),
                    $f('heading', 'Heading', 'text', 'Find your ticket'),
                    $f('lead', 'Subtitle', 'text', 'Enter the reference you received when you booked to see your status and download your ticket.'),
                    $photo(),
                ]],
                'form' => ['label' => 'Lookup form', 'fields' => [
                    $f('label', 'Field label', 'text', 'Booking reference'),
                    $f('button', 'Button', 'text', 'Find my booking'),
                    $f('help', 'Help text', 'text', 'Your reference starts with CB and has 8 characters, for example CB7K3M9Q. It was shown after you booked and sent to your email if you gave one.'),
                    $f('contact', 'Help line', 'text', 'Lost your reference? Contact us and we will help you find it.'),
                ]],
            ],
        ],
        'gallery' => [
            'label' => 'Gallery', 'icon' => '🖼️', 'url' => 'gallery', 'roles' => ['editor'],
            'desc' => 'Photo gallery. Photos and albums are managed under Gallery.',
            'related' => [['Manage photos', 'admin/gallery', 'gallery']],
            'sections' => [
                'hero' => ['label' => 'Page header', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Gallery'),
                    $f('heading', 'Heading', 'text', 'Moments from Chill & Bite'),
                    $photo(),
                ]],
                'misc' => ['label' => 'Notes', 'fields' => [
                    $f('empty', 'Message when there are no photos', 'text', 'Photos from our events will appear here.'),
                ]],
            ],
        ],
        'contact' => [
            'label' => 'Contact us', 'icon' => '✉️', 'url' => 'contact', 'roles' => ['editor'],
            'desc' => 'Contact details and message form. Phone, email and social links are in Settings.',
            'related' => [['Messages received', 'admin/inquiries?type=contact', 'inquiries'], ['Contact details & socials', 'admin/settings', 'settings']],
            'sections' => [
                'hero' => ['label' => 'Page header', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Contact us'),
                    $f('heading', 'Heading', 'text', "Let's talk"),
                    $f('lead', 'Subtitle', 'text', 'Booking questions, partnerships or just saying hello.'),
                    $photo(),
                ]],
                'info' => ['label' => 'Contact details block', 'fields' => [
                    $f('heading', 'Heading', 'text', 'Find us'),
                    $f('hours', 'Opening line', 'text', 'Every first Sunday · 12:00 PM onwards'),
                ]],
                'form' => ['label' => 'Message form', 'fields' => [
                    $f('button', 'Submit button', 'text', 'Send message'),
                ]],
            ],
        ],
    ];
    return $pages;
}

/** Flat map "page.section.field" => field definition (with type/default/label). */
function content_fields(): array
{
    static $flat = null;
    if ($flat !== null) return $flat;
    $flat = [];
    foreach (site_pages() as $pid => $page)
        foreach ($page['sections'] as $sid => $sec)
            foreach ($sec['fields'] as $fd) $flat["$pid.$sid.{$fd['key']}"] = $fd;
    return $flat;
}

/** Can the signed-in user edit this page? System Administrators can edit all; others per the page's roles. */
function can_edit_page(string $pageId): bool
{
    $u = current_user();
    $page = site_pages()[$pageId] ?? null;
    if (!$u || !$page) return false;
    return $u['role'] === 'admin' || in_array($u['role'], $page['roles'], true);
}

/* ---------- Reading content (used by the public views) ---------- */
function content_overrides(): array
{
    static $db = null;
    if ($db === null) {
        try { $db = array_column(rows('SELECT `key`, `value` FROM site_content'), 'value', 'key'); }
        catch (Throwable $e) { $db = []; }
    }
    return $db;
}
/** Raw text for a key: saved override, otherwise the built-in default. */
function ct(string $key): string
{
    $v = content_overrides()[$key] ?? '';
    return $v !== '' ? $v : (string)(content_fields()[$key]['default'] ?? '');
}
/** HTML-escaped text. */
function cth(string $key): string { return h(ct($key)); }
/** Paragraph(s) of escaped text. */
function ctp(string $key): string { return paragraphs(ct($key)); }
/** Non-empty lines as an array. */
function ctl(string $key): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', ct($key))), fn($l) => $l !== ''));
}
/** Lines split on "|" into trimmed parts, e.g. "Title | description". */
function ctrows(string $key, int $parts = 2): array
{
    $out = [];
    foreach (ctl($key) as $line) {
        $p = array_map('trim', explode('|', $line, $parts));
        while (count($p) < $parts) array_unshift($p, '');
        $out[] = $p;
    }
    return $out;
}
/** URL of an image field (override upload or built-in default); '' when none. */
function cti(string $key): string
{
    $v = ct($key);
    return $v !== '' ? upload_url($v) : '';
}
