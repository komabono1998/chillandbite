<?php
declare(strict_types=1);
// Adds sample Gulu Chronicles stories (short original summaries with credit and link to the original outlet).
// Safe to run more than once: stories are matched by source_url.
require __DIR__ . '/../app/bootstrap.php';

$stories = [
    ['Uganda News', 'news', 'Museveni cautions MPs over digital number plates reports', '2026-11-02 09:00:00', 'The Independent (Kampala)', 'https://allafrica.com/stories/202611020057.html',
     "President Yoweri Museveni has warned members of parliament against circulating inaccurate reports about the digital number plates programme, according to a report by The Independent (Kampala) published on 2 November 2026.\n\nThe President's message was aimed at lawmakers who have been discussing the programme in public. Readers who want the full details, including exactly what was said, should read the original report linked below."],
    ['Entertainment', 'news', 'Comedian Kapere Lufotose has died', '2026-11-02 10:00:00', 'Nile Post', 'https://allafrica.com/stories/202611020054.html',
     "Nile Post reports that Ugandan comedian Kapere Lufotose has died. The report was published on 2 November 2026.\n\nThe Gulu Chronicles sends condolences to his family, friends and fans, and to the comedy community that knew him. We will add more once the family and official sources share details.\n\nThis is a short summary. Please read the original report linked below for the latest information."],
    ['Uganda News', 'news', 'Government says it is working with Uganda on Lake Victoria harassment', '2026-11-02 08:00:00', 'Capital FM (via allAfrica)', 'https://allafrica.com/stories/202611020037.html',
     "Permanent Secretary Omollo says the government is engaging Uganda to find a permanent solution to harassment on Lake Victoria, Capital FM reported on 2 November 2026.\n\nThe lake is shared by Uganda, Kenya and Tanzania, and fishing communities depend on it for their income. We will update this story as more official information is released."],
    ['Business', 'news', 'Uganda\'s trade deficit with East African partners widens to $809 million', '2026-10-30 12:00:00', 'The Independent (Kampala)', 'https://allafrica.com/stories/202610300235.html',
     "Uganda's trade deficit with its East African Community partners has grown to $809 million as imports surge, The Independent (Kampala) reported on 30 October 2026.\n\nA wider deficit means Uganda is buying more from its regional neighbours than it is selling to them. The original report has the full figures and analysis."],
    ['Business', 'news', 'Museveni asks Egyptian investors to back Uganda\'s infrastructure', '2026-10-30 11:00:00', 'Nile Post', 'https://allafrica.com/stories/202610300184.html',
     "President Museveni is encouraging Egyptian investors to finance infrastructure and value addition projects in Uganda, according to Nile Post on 30 October 2026.\n\nValue addition means processing raw products such as farm produce and minerals inside Uganda instead of exporting them unprocessed. Read the original report for the full details."],
    ['Business', 'opinion', 'Opinion: the shilling crisis and the cost of growth without better livelihoods', '2026-10-30 10:00:00', 'The Independent (Kampala)', 'https://allafrica.com/stories/202610300307.html',
     "An opinion piece in The Independent (Kampala) on 30 October 2026 argues that the recent fall of the Uganda shilling shows the cost of economic growth that has not improved people's day-to-day livelihoods.\n\nThis is the writer's view, not a statement of fact by The Gulu Chronicles. Read the full opinion at the link below and form your own view."],
];

$ins = 0;
foreach ($stories as [$cat, $label, $title, $when, $src, $url, $body]) {
    if (val('SELECT id FROM articles WHERE source_url = ?', [$url])) continue;
    $cid = val('SELECT id FROM article_categories WHERE name = ?', [$cat]) ?: null;
    q("INSERT INTO articles (category_id, title, excerpt, body, label, status, published_at, slug, source_name, source_url) VALUES (?,?,?,?,?,'published',?,?,?,?)",
        [$cid, $title, mb_substr(explode("\n", $body)[0], 0, 300), $body, $label, $when, unique_slug('articles', $title), $src, $url]);
    $ins++;
}
echo "Inserted $ins stories\n";
