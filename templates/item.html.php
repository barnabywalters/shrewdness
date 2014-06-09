<div class="item">
	<p class="item-author">
		<a href="<?= $item['author']['url'] ?>">
			<?php if ($item['author']['photo']): ?>
			<img class="item-author-photo" alt="" src="<?= $item['author']['photo'] ?>">
			<?php endif ?>
			<?= $item['author']['name'] ?>
		</a>
	</p>

	<div class="item-content">
		<?= $item['displayContent'] ?>
	</div>

	<div class="item-foot">
		<a class="item-url" href="<?= $item['url'] ?>">
			<time class="item-published" datetime="<?= $item['published']->format(DateTime::W3C) ?>"><?= $item['published']->format('Y-m-d H:i:s Z') ?></time>
		</a>

		<div class="item-actions">
			<button class="reply-button">Reply</button>
		</div>
	</div>
</div>
