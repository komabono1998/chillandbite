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
            'desc' => 'The first page visitors see: hero, next event, host, food, comedy, partners and the final call to action.',
            'related' => [['Events', 'admin/events', 'events'], ['Gallery', 'admin/gallery', 'gallery'], ['Sponsors', 'admin/advertising', 'advertising']],
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
                    $f('photo', 'Host portrait', 'image', 'img/host-mc-kash-crown.jpg', 'Portrait photo, best at 3:4 (for example 600 x 800).'),
                ]],
                'comedy' => ['label' => 'Comedy section', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Comedy'),
                    $f('heading', 'Heading', 'text', 'Laugh with Owakabi: Life Is Better with a Little Humour!'),
                    $f('shows_title', 'Shows list title', 'text', 'Upcoming comedy shows'),
                    $f('blurb', 'Short text', 'textarea', 'Guest comedians, behind-the-scenes clips and show announcements. Want MC Kash Owakabi at your event?'),
                    $f('btn_book', 'Button 1', 'text', 'Book a performance'),
                    $f('btn_more', 'Button 2', 'text', 'More comedy'),
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
            'label' => 'About us', 'icon' => 'ℹ️', 'url' => 'about', 'roles' => ['editor'],
            'desc' => 'Company profile: key facts, story, what we do, mission and values, the team, partners and FAQs.',
            'related' => [['Partner applications', 'admin/partners', 'advertising'], ['Contact details & socials', 'admin/settings', 'settings']],
            'sections' => [
                'hero' => ['label' => 'Page header', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'About us'),
                    $f('heading', 'Heading', 'text', 'About Chill & Bite MEATINGS'),
                    $f('lead', 'Subtitle', 'text', setting('tagline')),
                    $f('btn_primary', 'Button 1', 'text', 'Reserve your place'),
                    $f('btn_secondary', 'Button 2', 'text', 'Contact us'),
                    $photo(),
                ]],
                'stats' => ['label' => 'Key facts bar', 'fields' => [
                    $f('items', 'Facts (one per line: value | label)', 'lines', "Every 1st Sunday | Monthly experience\n12 PM | Gates open\nGulu City | Revelation Cottages\n2026 | 1st Anniversary edition", 'Numbers such as 2026 count up when they appear.'),
                ]],
                'story' => ['label' => 'Our story', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Our story'),
                    $f('heading', 'Heading', 'text', 'The story of Chill & Bite MEATINGS'),
                    $f('p1', 'Paragraph 1', 'textarea', 'Chill & Bite MEATINGS is a premium lifestyle and entertainment experience held every first Sunday of the month at Revelation Cottages, Gulu City. It brings together professionals, entrepreneurs, creatives, businesses and entertainment lovers for food, music, comedy, outdoor games and real conversations.'),
                    $f('p2', 'Paragraph 2', 'textarea', 'It is hosted by MC Kash Owakabi and organized by Owakabi & The Geng, a team passionate about building opportunities for Northern Uganda through shared experiences.'),
                    $f('quote', 'Highlighted line', 'text', 'Good food. Great people. Unforgettable connections.'),
                    $f('photo', 'Main photo', 'image', 'img/photos/photo-10.jpg'),
                    $f('photo2', 'Second photo', 'image', 'img/photos/photo-44.jpg'),
                    $f('photo3', 'Third photo', 'image', 'img/photos/photo-02.jpg'),
                ]],
                'pillars' => ['label' => 'What we do', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'What we do'),
                    $f('heading', 'Heading', 'text', 'One team, three ways to connect'),
                    $f('intro', 'Introduction', 'text', 'Beyond the monthly event, Owakabi & The Geng runs a small ecosystem of brands for Northern Uganda.'),
                    $f('cards', 'Cards (one per line: emoji | Title | text | page link | photo path)', 'lines',
                        "🔥 | Chill & Bite MEATINGS | The monthly lifestyle and corporate networking experience at Revelation Cottages. | events | img/photos/photo-03.jpg\n" .
                        "😂 | Laugh with Owakabi | Comedy shows, video highlights and performance bookings with MC Kash Owakabi. | laugh | img/photos/photo-12.jpg\n" .
                        "🤝 | Brand partnerships | Sponsorships, activations and advertising in front of an engaged audience. | advertise | img/photos/photo-42.jpg",
                        'Page link is a page name such as events, laugh or advertise. Photo path starts with img/photos/.'),
                ]],
                'mission' => ['label' => 'Mission, vision & values', 'fields' => [
                    $f('mission_title', 'Mission title', 'text', 'Our mission'),
                    $f('mission', 'Mission', 'textarea', 'To connect people through food, music, comedy and shared experiences, creating real relationships and real opportunities across Northern Uganda.'),
                    $f('vision_title', 'Vision title', 'text', 'Our vision'),
                    $f('vision', 'Vision', 'textarea', "To be Northern Uganda's most loved lifestyle and networking brand: a place where professionals, creatives and businesses meet and grow together."),
                    $f('values_heading', 'Values heading', 'text', 'What we stand for'),
                    $f('values', 'Values (one per line: Title | text)', 'lines', "Connection | Every event is built around meeting people and starting conversations.\nCommunity | We grow together with Gulu City and the wider Northern Uganda region.\nQuality | Great food, sound and hospitality, done properly every time.\nOpportunity | Space for individuals and brands to network, market and grow."),
                ]],
                'expect' => ['label' => 'What to expect', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'The experience'),
                    $f('heading', 'Heading', 'text', 'What to expect'),
                    $f('list', 'List (one per line)', 'lines', "Great food and drinks in a relaxed outdoor setting\nNetworking with people across industries\nLive music, comedy and entertainment\nOutdoor games and social experiences\nOpportunities for brands to connect with a lively audience"),
                ]],
                'team' => ['label' => 'Team & host', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'The people'),
                    $f('heading', 'Heading', 'text', 'Owakabi & The Geng'),
                    $f('text', 'Organizer text', 'textarea', 'The creative team behind Chill & Bite MEATINGS, and Laugh with Owakabi.'),
                    $f('button', 'Organizer button', 'text', 'See upcoming events'),
                    $f('host_role', 'Host title', 'text', 'Host & master of ceremonies'),
                    $f('host_bio', 'Host biography', 'textarea', setting('host_bio', 'From electrifying crowds to creating memorable experiences, MC Kash Owakabi brings charisma, humor, and exceptional audience engagement to every event.')),
                ]],
                'partners' => ['label' => 'Partners', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Partners'),
                    $f('heading', 'Heading', 'text', 'Brands that grow with us'),
                    $f('intro', 'Introduction', 'text', 'Companies and individuals come to Chill & Bite to network, market their events and promote their products and services.'),
                    $f('button', 'Button', 'text', 'Become a partner'),
                    $f('empty', 'Message when there are no partners yet', 'text', 'Be one of the first brands featured here.'),
                ]],
                'faq' => ['label' => 'Frequently asked questions', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'FAQ'),
                    $f('heading', 'Heading', 'text', 'Frequently asked questions'),
                    $f('items', 'Questions (one per line: Question | Answer)', 'lines',
                        "When and where does Chill & Bite MEATINGS take place? | Usually on the first Sunday of every month at Revelation Cottages, Gulu City. Gates open at 12:00 PM. Special editions may fall on other Sundays, so check the Events page for exact dates.\n" .
                        "How do I book a place or a table? | Open the event page and choose Reserve now. Pick a reservation type or a table package, enter your details and confirm. Your booking is approved by our team and your QR ticket is issued.\n" .
                        "How do I get my ticket? | Once your booking is confirmed we send your ticket by email or WhatsApp. You can also enter your booking reference on the Track your booking page to view or download it at any time.\n" .
                        "Can my company sponsor or exhibit? | Yes. Brands come to network and market their events, products and services. Apply on the Become a partner page or request an advertising proposal.\n" .
                        "Is there an age limit? | Some events are 18 and over, especially those with alcohol sponsors. The rules are shown on each event page.\n" .
                        "How can I contact the team? | Use the Contact page, or call or WhatsApp the number shown in the footer of this website."),
                ]],
                'cta' => ['label' => 'Closing call to action', 'fields' => [
                    $f('heading', 'Heading', 'text', 'Ready to be part of the next one?'),
                    $f('text', 'Text', 'text', 'Reserve your place, bring your team, or showcase your brand to Gulu City.'),
                    $f('btn1', 'Button 1', 'text', 'Reserve your place'),
                    $f('btn2', 'Button 2', 'text', 'Partner with us'),
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
            'desc' => 'The comedy page: host profile, services, videos, shows, FAQs and the performance booking form.',
            'related' => [['Comedy videos', 'admin/comedy', 'comedy'], ['Comedy events', 'admin/events', 'events'], ['Performance requests', 'admin/inquiries?type=performance', 'inquiries']],
            'sections' => [
                'hero' => ['label' => 'Page header', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Laugh with Owakabi'),
                    $f('heading', 'Heading', 'text', 'Life is better with a little humour!'),
                    $f('lead', 'Subtitle', 'text', 'Stand-up comedy and professional hosting by MC Kash Owakabi, for corporate events, weddings, launches, concerts and private celebrations across Uganda.'),
                    $f('btn_shows', 'Button 1', 'text', 'See upcoming shows'),
                    $f('btn_book', 'Button 2', 'text', 'Book a performance'),
                    $f('badge', 'Photo badge', 'text', 'MC & stand-up comedian'),
                    $f('trust', 'Highlights strip (one per line)', 'lines', "Live stand-up comedy\nProfessional MC and hosting\nCorporate-ready shows\nWeddings, launches and parties\nBased in Gulu, available across Uganda"),
                    $photo(),
                ]],
                'host' => ['label' => 'Meet the host', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Meet the host'),
                    $f('heading', 'Heading', 'text', 'MC Kash Owakabi'),
                    $f('bio', 'Paragraph 1', 'textarea', 'MC Kash Owakabi is the voice and face of Chill & Bite MEATINGS in Gulu City. He is a charismatic master of ceremonies and stand-up comedian who keeps a room laughing, relaxed and fully involved.'),
                    $f('bio2', 'Paragraph 2', 'textarea', 'He is the founder of Owakabi & The Geng, the creative team behind Chill & Bite MEATINGS, and Laugh with Owakabi. Whether it is a packed outdoor crowd or a boardroom dinner, he adapts the humour to the audience.'),
                    $f('points', 'Highlights (one per line)', 'lines', "Charismatic host and master of ceremonies\nStand-up comedy and live audience engagement\nClean, audience-friendly humour that suits corporate and family events\nFounder of Owakabi & The Geng"),
                    $f('photo', 'Host photo', 'image', 'img/host-mc-kash-crown.jpg'),
                ]],
                'services' => ['label' => 'What we offer', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Book MC Kash'),
                    $f('heading', 'Heading', 'text', 'Comedy and hosting for every occasion'),
                    $f('intro', 'Introduction', 'text', 'Choose the kind of event you are planning and we will take it from there.'),
                    $f('cards', 'Cards (one per line: emoji | Title | text | form value: Corporate, Wedding, Concert, Launch, Private or Other)', 'lines',
                        "🏢 | Corporate events | Staff parties, year-end functions, team building and company dinners. | Corporate\n" .
                        "💍 | Weddings & celebrations | A warm, funny MC who keeps the programme flowing and the guests happy. | Wedding\n" .
                        "🎤 | Concerts & festivals | Crowd energy, smooth transitions and hosting between acts. | Concert\n" .
                        "🚀 | Product & brand launches | Introduce your brand with a host who gets the crowd talking. | Launch\n" .
                        "🎉 | Private parties | Birthdays, anniversaries and reunions with a good laugh built in. | Private\n" .
                        "🎙️ | Hosting & MC only | Just need a professional MC for your programme? We can do that too. | Other"),
                ]],
                'videos' => ['label' => 'Video library', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Video library'),
                    $f('heading', 'Heading', 'text', 'Watch & laugh'),
                    $f('intro', 'Introduction', 'text', 'Clips, highlights and behind-the-scenes moments. Tap a video to play it here.'),
                    $f('empty', 'Message when there are no videos', 'text', 'Videos are coming soon. Follow us on social media for the latest clips.'),
                ]],
                'shows' => ['label' => 'Upcoming performances', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Comedy events'),
                    $f('heading', 'Heading', 'text', 'Upcoming performances'),
                    $f('empty', 'Message when there are no shows', 'text', 'No public shows are scheduled right now. Check back soon, or book a private performance for your own event.'),
                ]],
                'process' => ['label' => 'How booking works', 'fields' => [
                    $f('heading', 'Heading', 'text', 'How booking works'),
                    $f('steps', 'Steps (one per line: Title | text)', 'lines',
                        "Send your request | Tell us the date, place, type of event and audience size.\n" .
                        "We get back to you | Our team contacts you to discuss your programme and the details.\n" .
                        "Confirm the details | Agree the programme, timing and arrangements for your event.\n" .
                        "Enjoy the laughs | MC Kash arrives ready to make your event one to remember."),
                ]],
                'faq' => ['label' => 'Frequently asked questions', 'fields' => [
                    $f('heading', 'Heading', 'text', 'Questions we are often asked'),
                    $f('items', 'Questions (one per line: Question | Answer)', 'lines',
                        "What kind of events can I book MC Kash for? | Corporate events, weddings, concerts and festivals, product launches, private parties and any programme that needs a professional host.\n" .
                        "Is the comedy suitable for all audiences? | Yes. The humour is audience-friendly, and MC Kash adapts it to your crowd, whether that is a corporate team, a family gathering or a lively outdoor event.\n" .
                        "Can you perform outside Gulu? | Yes. Tell us your location in the form and we will confirm availability and arrangements.\n" .
                        "How far ahead should I book? | As early as you can, because popular dates fill up. Send your request with your event date and we will confirm availability.\n" .
                        "How do I get a quote? | Fill in the request form below or call or WhatsApp us. We will discuss your event and come back to you with the details."),
                ]],
                'booking' => ['label' => 'Performance booking form', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Performance booking'),
                    $f('heading', 'Heading', 'text', 'Book MC Kash Owakabi'),
                    $f('intro', 'Introduction', 'text', 'Organizations, brands, event organizers and individuals: tell us about your event and we will get back to you.'),
                    $f('button', 'Submit button', 'text', 'Send request'),
                    $f('side_title', 'Side panel title', 'text', 'Prefer to talk?'),
                    $f('side_text', 'Side panel text', 'text', 'Call or WhatsApp our team and we will take your details directly.'),
                ]],
            ],
        ],
        'djs' => [
            'label' => 'Podium DJs', 'icon' => '🎧', 'url' => 'djs', 'roles' => ['editor', 'marketing', 'dj_manager'],
            'desc' => 'Book a DJ for an event, meet the roster and listen to mixes from Podium DJs and Chill & Bite MEATINGS. DJs and mixes are managed under DJs & mixes.',
            'related' => [['Manage DJs & mixes', 'admin/djs', 'djs'], ['DJ booking requests', 'admin/inquiries?type=dj', 'inquiries']],
            'sections' => [
                'hero' => ['label' => 'Page header', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Podium DJs'),
                    $f('heading', 'Heading', 'text', 'The sound that makes your event move.'),
                    $f('lead', 'Subtitle', 'textarea', 'Book professional DJs for weddings, corporate functions, club nights, concerts and private parties, and listen to mixes from Podium DJs and Chill & Bite MEATINGS.'),
                    $f('btn_book', 'Button 1', 'text', 'Book a DJ'),
                    $f('btn_mixes', 'Button 2', 'text', 'Listen to mixes'),
                    $f('trust', 'Highlights strip (one per line)', 'lines', "Weddings & celebrations\nCorporate functions\nClub & bar nights\nConcerts & festivals\nSchool & campus events\nSound and lights available\nGulu-based, available across Uganda"),
                    $photo(),
                ]],
                'services' => ['label' => 'What we play for', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Events & functions'),
                    $f('heading', 'Heading', 'text', 'A DJ for every kind of event'),
                    $f('intro', 'Introduction', 'text', 'Pick your event type and we will match you with the right DJ and set-up.'),
                    $f('cards', 'Cards (one per line: emoji | Title | text | form value: Wedding, Corporate, Club, Concert, Private, School or Other)', 'lines',
                        "💍 | Weddings & receptions | Music for the ceremony, the dinner and the dance floor, planned with you. | Wedding\n" .
                        "🏢 | Corporate functions | Year-end parties, launches and dinners with the right mood all evening. | Corporate\n" .
                        "🍹 | Club & bar nights | High-energy sets that keep the floor full from the first song. | Club\n" .
                        "🎪 | Concerts & festivals | Opening sets, interludes and warm-ups that build the crowd. | Concert\n" .
                        "🎂 | Private parties | Birthdays, anniversaries and house parties with your playlist and ours. | Private\n" .
                        "🎓 | School & campus events | Fun, clean sets for graduations, galas and student events. | School"),
                ]],
                'mixes' => ['label' => 'Mixes', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'Mixtapes'),
                    $f('heading', 'Heading', 'text', 'Listen to the mixes'),
                    $f('intro', 'Introduction', 'text', 'Fresh mixes from Podium DJs and live sets from Chill & Bite MEATINGS. Press play and keep browsing.'),
                    $f('empty', 'Message when there are no mixes', 'text', 'New mixes are on the way. Check back soon.'),
                ]],
                'roster' => ['label' => 'DJ roster', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'The lineup'),
                    $f('heading', 'Heading', 'text', 'Meet the DJs'),
                    $f('intro', 'Introduction', 'text', 'Choose your favourite or let us match you with the best DJ for your event.'),
                    $f('empty', 'Message when there are no DJs listed', 'text', 'Our lineup is being updated. Send a request and we will match you with a DJ for your event.'),
                ]],
                'process' => ['label' => 'How booking works', 'fields' => [
                    $f('heading', 'Heading', 'text', 'How booking works'),
                    $f('steps', 'Steps (one per line: Title | text)', 'lines',
                        "Send your request | Tell us the date, venue, type of event and the hours you need.\n" .
                        "We confirm | We check the DJ's availability and discuss your music and equipment.\n" .
                        "Plan the set | Share your must-play songs and the mood you want.\n" .
                        "Party time | Your DJ arrives, sets up and keeps the energy high."),
                ]],
                'faq' => ['label' => 'Frequently asked questions', 'fields' => [
                    $f('heading', 'Heading', 'text', 'Questions we are often asked'),
                    $f('items', 'Questions (one per line: Question | Answer)', 'lines',
                        "Can I choose a specific DJ? | Yes. Pick a DJ in the booking form, or choose \"Any DJ\" and we will match you with the right one.\n" .
                        "Do you bring the sound system and lights? | Tell us in the form whether you need a sound system and lights or your venue already has them, and we will confirm what is available.\n" .
                        "Can I send a playlist or must-play songs? | Yes. Add them in the extra details, or share them when we confirm your booking.\n" .
                        "Do you play outside Gulu? | Yes. Tell us your location and we will confirm availability and arrangements.\n" .
                        "How far ahead should I book? | As early as possible, especially for weekends, weddings and holidays. Send your request with the date and we will confirm availability."),
                ]],
                'booking' => ['label' => 'DJ booking form', 'fields' => [
                    $f('eyebrow', 'Small label', 'text', 'DJ booking'),
                    $f('heading', 'Heading', 'text', 'Book a DJ for your event'),
                    $f('intro', 'Introduction', 'text', 'Tell us about your event and we will get back to you to confirm availability and details.'),
                    $f('button', 'Submit button', 'text', 'Send DJ request'),
                    $f('side_title', 'Side panel title', 'text', 'Prefer to talk?'),
                    $f('side_text', 'Side panel text', 'text', 'Call or WhatsApp our team and we will take your details directly.'),
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
                    $f('ads_title', 'Advertise: tab title', 'text', 'Advertise with us'),
                    $f('ads_tag', 'Advertise: short line', 'text', 'Put your brand in front of our online audience.'),
                    $f('ads_cards', 'Advertise: cards (one per line: Title | description)', 'lines', "Website Banner Advertising | Advertise on selected website pages.
Sponsored Articles | Clearly labelled promotional articles.
Social Media Promotion | Campaigns across our social channels.
Product Showcases | Put products and services in front of guests."),
                    $f('spons_title', 'Sponsor: tab title', 'text', 'Sponsor & partner'),
                    $f('spons_tag', 'Sponsor: short line', 'text', 'Be part of the event itself and build a lasting partnership.'),
                    $f('spons_cards', 'Sponsor: cards (one per line: Title | description)', 'lines', "Event Sponsorship | Sponsor a monthly event or a specific activity.
Brand Activations | Engage attendees with approved activities.
Comedy Show Sponsorship | Sponsor Laugh with Owakabi performances.
Annual Partnerships | Longer-term promotional partnerships."),
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

/* ---------- Editable pictures: photo library and brand images ---------- */
/** Replacement for built-in photo number $n (uploaded in Admin > Pictures), or null to use the bundled file. */
function photo_override(int $n): ?string
{
    $v = content_overrides()['photolib.' . $n] ?? '';
    return $v !== '' ? upload_url($v) : null;
}
function brand_logo(): string
{
    $v = content_overrides()['brand.logo'] ?? '';
    return $v !== '' ? upload_url($v) : asset('img/logo.png');
}
function brand_favicon(): string
{
    $v = content_overrides()['brand.favicon'] ?? '';
    return $v !== '' ? upload_url($v) : asset('img/favicon.png');
}
/** Absolute https URL for a site-relative URL such as /chillandbite/uploads/x.png. */
function abs_from(string $u): string
{
    if (preg_match('#^https?://#i', $u)) return $u;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $u;
}
