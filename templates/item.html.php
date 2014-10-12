<div class="item <?= $item['name'] ? 'named-item' : '' ?>">
	<?php if (count($item['in-reply-to']) > 0): $irt = $item['in-reply-to'][0]; ?>
		<?php if (is_string($irt)): ?>
	<div class="item-reply-context minimal-context">
		<abbr title="Post in reply to">↪</abbr> <a class="item-reply-context-url" href="<?= htmlspecialchars($irt) ?>"><?= htmlspecialchars(Taproot\Shrewdness\removeScheme($irt)) ?></a>
		<?php else: ?>
	<div class="item-reply-context expanded-context">
		<abbr title="Post in reply to">↪</abbr>

		<?php if ($irt['author']['photo']): ?>
			<a href="<?= htmlspecialchars($irt['author']['url']) ?>"><img class="item-reply-context-author-photo" alt="" src="<?= htmlspecialchars($irt['author']['photo']) ?>"></a>
		<?php endif ?>
			<a href="<?= htmlspecialchars($irt['author']['url']) ?>"><?= trim(htmlspecialchars($irt['author']['name'])) ?></a>:

		<?php if ($irt['name']): ?>
			<a class="item-reply-context-name" href="<?= htmlspecialchars($irt['url']) ?>"><?= trim(htmlspecialchars($irt['name'])) ?></a>
		<?php else: ?>
			<a class="item-reply-context-content" href="<?= htmlspecialchars($irt['url']) ?>"><?= trim(htmlspecialchars($irt['text'])) ?></a>
		<?php endif ?>
		<?php endif ?>
	</div>
	<?php endif ?>
	<div class="item-author">
		<?php if ($item['author']['photo']): ?>
		<a href="<?= htmlspecialchars($item['author']['url']) ?>"><img class="item-author-photo" alt="" src="<?= htmlspecialchars($item['author']['photo']) ?>"></a>
		<?php endif ?>

		<?php if ($item['name']): ?>
		<div class="item-name-container">
			<a href="<?= htmlspecialchars($item['author']['url']) ?>" class="author-name"><?= trim(htmlspecialchars($item['author']['name'])) ?></a>
			<h1 class="item-name"><a href="<?= htmlspecialchars($item['url']) ?>"><?= trim(htmlspecialchars($item['name'])) ?></a></h1>
		</div>
		<?php else: ?>
			<a href="<?= htmlspecialchars($item['author']['url']) ?>"><?= trim(htmlspecialchars($item['author']['name'])) ?></a>
		<?php endif ?>
	</div>

	<?php if (isset($item['photo']) and strpos($item['display_content'], $item['photo']) === false): ?>
	<img class="item-photo" src="<?= htmlspecialchars($item['photo']) ?>" alt="" /><?php endif ?>
	
	<div class="item-content">
		<?= trim($item['display_content']) ?>
	</div>

	<div class="item-foot">
		<?php if (!empty($item['logo'])): ?>
		<img class="item-logo" src="<?= htmlspecialchars($item['logo']) ?>" alt="" />
		<?php endif ?>

		<a class="item-url" href="<?= htmlspecialchars(trim($item['url'])) ?>">
			<time class="item-published" datetime="<?= trim($item['published']->format(DateTime::W3C)) ?>"><?= $item['published']->format('Y-m-d H:i T') ?></time>
		</a>

		<div class="item-actions">
			<?php if (!empty($token['micropub_endpoint'])): ?>
			<button class="reply-button">Reply</button>
			<?php else: ?>
			<indie-action do="reply" with="<?= htmlspecialchars($item['url']) ?>"></indie-action>
			<?php endif ?>
		</div>
	</div>

	<div class="item-action-panel collapsing-panel collapsed">
		<div class="reply-panel">
			<textarea class="reply-content"></textarea>
			<p><button class="reply-post-button">Post</button></p>
		</div>
	</div>
</div>
