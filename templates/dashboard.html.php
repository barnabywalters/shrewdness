<div class="x-scroll-wrapper">
	<div class="columns">
		<?php foreach ($columns as $column): ?>
			<?= $render('column.html', ['column' => $column['_source']]) ?>
		<?php endforeach ?>

		<?= $render('new-column.html') ?>
	</div>
</div>
