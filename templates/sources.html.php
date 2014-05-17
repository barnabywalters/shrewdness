<?php foreach ($sources as $source): ?>
	<p class="column-source" title="Posts from <?= $source['topic'] ?>">
		<?php if ($source['profile'] !== null): ?>
			<?php if (!empty($source['photo'])): ?>
			<img class="column-source-photo" src="<?= $source['profile']['photo'] ?>" />
			<?php endif ?>

			<?= $source['profile']['name'] ?>

			<span class="source-domain"><?= parse_url($source['profile']['url'], PHP_URL_HOST) ?></span>
		<?php else: ?>
			<?= $source['topic'] ?>
		<?php endif ?>
		<button class="remove-source">x</button>
	</p>
<?php endforeach ?>
