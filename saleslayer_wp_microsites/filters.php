<?php

    require_once(SLYR__PLUGIN_DIR.'../../../wp-config.php');
    require_once(SLYR__PLUGIN_DIR.'admin/SlPlugin.class.php');

	$backend=new SlPlugin();

    $success=$error='';

    if (isset($_POST['submit'])) {

		if (!empty($_POST['filtersCheck'])) {

	    	$backend->updateProductFilters($_POST['filtersCheck']);
    		$success="Filters updated successfully!";

		} else {

    		$error="Select at least one filter.";
		}
    }

    $filters=$backend->getProductFilters();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Configuración</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div id="slyr_catalogue_admin">
	<div class="container filters">

		<h1><?php echo SLYR_name; ?> plugin <small>/ filters configuration</small></h1>

        <h6>Please specify which filters are applied to products.</h6>

        <form method="POST" id="filter_form">
        	
        	<?php if($error): ?>
                <div class="dialog dialog-warning">
                   <?php echo $error ?>
                </div>
            <?php endif ?>

        	<?php if($success): ?>
	            <div class="dialog dialog-success"><?php echo $success ?></div>
            <?php endif ?>

	        <?php if(!empty($filters)): ?>

			        <table class="table-bordered">
			        	<thead>
				        	<tr>
				        		<th>Active</th>
				        		<th>Filter</th>
				        	</tr>
				        	<?php foreach ($filters as $key => $filter):  ?>
				        		<tr>
				        			<td class="center"><label for="<?php echo $filter->field ?>">
				        					<input type="checkbox" <?php echo ($filter->active*1 ? 'checked= "checked"' : '' ) ?> name="filtersCheck[]" value="<?php echo $filter->field ?>" id="<?php echo $filter->field ?>">
				        				</label>
				        			</td>
				        			<td style="text-transform:capitalize;"><?php echo str_replace('_', ' ', $filter->field) ?></td>
				        		</tr>
				        	<?php endforeach ?>
			        	</thead>
			        	<tbody id="filterList">
			        	</tbody>
			        </table>

		        <button href="#fakelink" class="button button-primary" type="submit" name="submit"><strong>Accept</strong></button>
	    	<?php else: ?>
	    		<div class="dialog dialog-warning">
                   <h7>Please, <a href="<?php echo admin_url() ?>admin.php?page=slyr_config"<?php echo SLYR_name; ?>>you must configure the connector before</a>.</h7>
                </div>
	    	<?php endif ?>
        </form>
	</div>
    </div>

</body>

</html>