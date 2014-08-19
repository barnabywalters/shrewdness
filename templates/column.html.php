<div class="column orderable-column editable-column" data-column-id="<?= $column['id'] ?>">
	<div class="column-header">
		<h1 class="column-name"><?= isset($column['name']) ? $column['name'] : 'New Column' ?></h1>

		<?php if ($column['id'] !== '_test'): ?>
		<button class="column-settings-button">Settings</button>

		<div class="column-settings collapsing-panel collapsed">
			<?php if (isset($column['sources'])): ?>
			<p class="column-settings-name">Sources:</p>
			<div class="column-sources">
				<div class="source-container">
					<?= $render('sources.html', ['sources' => $column['sources']]) ?>
				</div>
				<p><input class="new-source-url" /><button class="add-source">+</button></p>
			</div>
			<?php elseif (isset($column['search'])): ?>
				<p class="column-settings-name">Search:</p>
				<input type="search" class="column-search-term" value="<?= $column['search']['term'] ?>" />
			<?php endif ?>

			<p class="delete-column"><button class="delete-column-button">Delete Column</button></p>
		</div>
		<?php endif ?>
	</div>
	<div class="column-body">
		<?php if (isset($column['items'])): ?>
		<?php foreach ($column['items'] as $item): ?>
		<?= $render('item.html', ['item' => $item]) ?>
		<?php endforeach ?>
		<?php endif ?>
	</div>
</div>
