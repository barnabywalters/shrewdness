<div class="column orderable-column editable-column" data-column-id="<?= $column['id'] ?>">
	<div class="column-header">
		<h1 class="column-name"><?= isset($column['name']) ? $column['name'] : 'New Column' ?></h1>
		<button class="column-settings-button">Settings</button>

		<div class="column-settings">
			<p class="column-settings-name">Sources:</p>
			<div class="column-sources">
				<div class="source-container">
					<?= $render('sources.html', ['sources' => $column['sources']]) ?>
				</div>
				<p><input class="new-source-url" /><button class="add-source">+</button></p>
			</div>
		</div>
	</div>
	<div class="column-body">
		<?php foreach ($column['items'] as $item): ?>
		<?= $render('item.html', ['item' => $item]) ?>
		<?php endforeach ?>
	</div>
</div>
