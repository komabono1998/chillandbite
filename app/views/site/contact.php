<section class="page-hero"><div class="wrap"><span class="eyebrow gold"><?= cth('contact.hero.eyebrow') ?></span><h1><?= cth('contact.hero.heading') ?></h1><p class="lead"><?= cth('contact.hero.lead') ?></p></div></section>
<section class="section section-cream"><div class="wrap contact-layout">
  <div class="prose">
    <h2><?= cth('contact.info.heading') ?></h2>
    <p><strong><?= h(setting('venue')) ?></strong><br><?= h(setting('location')) ?><br><?= cth('contact.info.hours') ?></p>
    <?php if (setting('contact_phone')): ?><p>Phone: <a href="tel:<?= h(preg_replace('/\s+/', '', setting('contact_phone'))) ?>"><?= h(setting('contact_phone')) ?></a></p><?php endif; ?>
    <?php if (setting('whatsapp_group')): ?><p><a class="btn btn-primary" href="<?= h(setting('whatsapp_group')) ?>" target="_blank" rel="noopener">Join our WhatsApp group</a></p><?php endif; ?>
    <?php if (setting('whatsapp')): ?><p><a class="btn btn-ghost-dark btn-sm" target="_blank" rel="noopener" href="<?= h(wa_link(setting('whatsapp'))) ?>">Chat on WhatsApp</a></p><?php endif; ?>
    <?php if (setting('contact_email')): ?><p>Email: <a href="mailto:<?= h(setting('contact_email')) ?>"><?= h(setting('contact_email')) ?></a></p><?php endif; ?>
    <p><?php foreach (social_links() as $n => $u): ?><a href="<?= h($u) ?>" target="_blank" rel="noopener"><?= h($n) ?></a> · <?php endforeach; ?></p>
  </div>
  <form class="form-card" method="post" action="<?= url('contact') ?>"><?= csrf_field() ?><?php honeypot(); ?>
    <?php field('name', 'Your name', ['required' => true]); field('phone', 'Telephone', ['type' => 'tel']); field('email', 'Email', ['type' => 'email']); field('subject', 'Subject'); field('message', 'Message', ['type' => 'textarea', 'required' => true]); ?>
    <button class="btn btn-primary btn-lg"><?= cth('contact.form.button') ?></button>
  </form>
</div></section>
