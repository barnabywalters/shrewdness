<?php foreach ($sources as $source): ?>
	<p class="column-source" title="Posts from <?= $source['topic'] ?>">
		<?php if ($source['profile'] !== null): ?>
			<?php if (!empty($source['profile']['photo'])): ?>
			<img class="column-source-photo" src="<?= $source['profile']['photo'] ?>" />
			<?php endif ?>
		<?php endif ?>

		<?= $source['topic'] ?>

		<button class="remove-source" data-url="<?= $source['topic'] ?>">x</button>
	</p>
<?php endforeach ?>
