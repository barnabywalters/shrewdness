<div class="item <?= $item['name'] ? 'named-item' : '' ?>">
	<div class="item-author">
		<a href="<?= $item['author']['url'] ?>">
			<?php if ($item['author']['photo']): ?>
			<img class="item-author-photo" alt="" src="<?= $item['author']['photo'] ?>">
			<?php endif ?>

			<?php if ($item['name']): ?>
			<div class="item-name-container">
				<span class="author-name"><?= trim($item['author']['name']) ?></span>
				<h1 class="item-name"><?= trim($item['name']) ?></h1>
			</div>
			<?php else: ?>
			<?= trim($item['author']['name']) ?>
			<?php endif ?>
		</a>
	</div>

	<?php if (isset($item['photo']) and strpos($item['display_content'], $item['photo']) === false): ?>
	<img class="item-photo" src="<?= $item['photo'] ?>" alt="" />
	<?php endif ?>

	<div class="item-content">
		<?= trim($item['display_content']) ?>
	</div>

	<div class="item-foot">
		<?php if (!empty($item['logo'])): ?>
		<img class="item-logo" src="<?= $item['logo'] ?>" alt="" />
		<?php endif ?>

		<a class="item-url" href="<?= trim($item['url']) ?>">
			<time class="item-published" datetime="<?= trim($item['published']->format(DateTime::W3C)) ?>"><?= $item['published']->format('Y-m-d H:i T') ?></time>
		</a>

		<div class="item-actions">
			<button class="reply-button">Reply</button>
		</div>
	</div>

	<div class="item-action-panel collapsing-panel collapsed">
		<div class="reply-panel">
			<textarea class="reply-content"></textarea>
			<p><button class="reply-post-button">Post</button></p>
		</div>
	</div>
</div>
