<section class="page-hero"><div class="wrap"><span class="eyebrow gold">Contact us</span><h1>Let's talk</h1><p class="lead">Booking questions, partnerships or just saying hello.</p></div></section>
<section class="section section-cream"><div class="wrap contact-layout">
  <div class="prose">
    <h2>Find us</h2>
    <p><strong><?= h(setting('venue')) ?></strong><br><?= h(setting('location')) ?><br>Every first Sunday · 12:00 PM onwards</p>
    <?php if (setting('contact_phone')): ?><p>Phone: <a href="tel:<?= h(preg_replace('/\s+/', '', setting('contact_phone'))) ?>"><?= h(setting('contact_phone')) ?></a></p><?php endif; ?>
    <?php if (setting('whatsapp')): ?><p><a class="btn btn-ghost-dark btn-sm" target="_blank" rel="noopener" href="https://wa.me/<?= h(preg_replace('/\D+/', '', setting('whatsapp'))) ?>">Chat on WhatsApp</a></p><?php endif; ?>
    <?php if (setting('contact_email')): ?><p>Email: <a href="mailto:<?= h(setting('contact_email')) ?>"><?= h(setting('contact_email')) ?></a></p><?php endif; ?>
    <p><?php foreach (social_links() as $n => $u): ?><a href="<?= h($u) ?>" target="_blank" rel="noopener"><?= h($n) ?></a> · <?php endforeach; ?></p>
  </div>
  <form class="form-card" method="post" action="<?= url('contact') ?>"><?= csrf_field() ?><?php honeypot(); ?>
    <?php field('name', 'Your name', ['required' => true]); field('phone', 'Telephone', ['type' => 'tel']); field('email', 'Email', ['type' => 'email']); field('subject', 'Subject'); field('message', 'Message', ['type' => 'textarea', 'required' => true]); ?>
    <button class="btn btn-primary btn-lg">Send message</button>
  </form>
</div></section>
