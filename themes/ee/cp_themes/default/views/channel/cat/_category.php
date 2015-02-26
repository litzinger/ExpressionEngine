<li class="tbl-list-item" data-id="<?=$category->cat_id?>">
	<div class="tbl-row">
		<div class="reorder"></div>
		<div class="txt">
			<div class="main">
				<b><?=$category->cat_name?></b>
			</div>
			<div class="secondary">
				<span class="faded">ID#</span> <?=$category->cat_id?> <span class="faded">/</span> <?=$category->cat_url_title?>
			</div>
		</div>
		<ul class="toolbar">
			<li class="edit"><a href="<?=cp_url('channel/cat/edit-cat/'.$category->cat_id)?>"></a></li>
		</ul>
		<div class="check-ctrl"><input type="checkbox" name="categories[]" value="<?=$category->cat_id?>" data-confirm="<?=lang('category') . ': <b>' . htmlentities($category->cat_name, ENT_QUOTES) . '</b>'?>"></div>
	</div>
	<?php $children = $category->getChildren()->sortBy(ee()->view->sort_column);
	if (count($children)): ?>
		<ul class="tbl-list">
			<?php foreach ($children as $child): ?>
				<?php $this->view('channel/cat/_category', array('category' => $child)); ?>
			<?php endforeach ?>
		</ul>
	<?php endif ?>
</li>