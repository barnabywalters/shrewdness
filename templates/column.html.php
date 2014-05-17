<div class="column orderable-column editable-column" data-column-id="<?= $column['_id'] ?>">
	<div class="column-header">
		<h1 class="column-name"><?= isset($column['_source']['name']) ? $column['_source']['name'] : 'New Column' ?></h1>
		<button class="column-settings-button">Settings</button>

		<div class="column-settings">
			<p class="column-settings-name">Sources:</p>
			<div class="column-sources">
				<div class="source-container">
					<?= $render('sources.html', ['sources' => $column['_source']['sources']]) ?>
				</div>
				<p><input class="new-source-url" /><button class="add-source">+</button></p>
			</div>
		</div>
	</div>
	<div class="column-body">

	</div>
</div>
