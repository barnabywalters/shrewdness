<div class="item">
	<p class="item-author">
		<a href="<?= $item['author']['url'] ?>">
			<?php if ($item['author']['photo']): ?>
			<img class="item-author-photo" alt="" src="<?= $item['author']['photo'] ?>">
			<?php endif ?>
			<?= trim($item['author']['name']) ?>
		</a>
	</p>

	<div class="item-content">
		<?= trim($item['display_content']) ?>
	</div>

	<div class="item-foot">
		<a class="item-url" href="<?= trim($item['url']) ?>">
			<time class="item-published" datetime="<?= trim($item['published']->format(DateTime::W3C)) ?>"><?= $item['published']->format('Y-m-d H:i T') ?></time>
		</a>

		<div class="item-actions">
			<button class="reply-button">Reply</button>
		</div>
	</div>
</div>
