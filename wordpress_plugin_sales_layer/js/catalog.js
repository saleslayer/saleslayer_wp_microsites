var baseURL= '',
	serviceURL='call_api.php',
	list_exclusion=[],
	lastIdCategory=0,
	slyr_cache=[];

list_exclusion.push('id');
list_exclusion.push('parent_id');
list_exclusion.push('id_product_types');
list_exclusion.push('id_catalogue');
list_exclusion.push('sl_product_name');
list_exclusion.push('sl_product_image');

function slyr_trim (a) {return a.replace(/^\s+|\s+$/g,'');}

function preloadShow() { $('#gnrl_ldng').show(); }
function preloadHide() { $('#gnrl_ldng').hide(); }

function loadFastMenu() {

	$.ajax({
		type: "POST",
		url: baseURL+serviceURL,
		data: { endpoint: "menu", web_url: slyr_page_home_url}
	})
	.done(function(result) {
		paintFastMenu(result, 'collapse_catalog_1');
	});
}

function paintFastMenu(fastMenuArray, divElem) {

	$.each( fastMenuArray, function( key, menuObj ) {
		if(menuObj.hasOwnProperty('submenu')) {
			var liAdd = '<li class="accordion-group" id="li_category_';
			liAdd = liAdd.concat(menuObj.ID);
			liAdd = liAdd.concat('"><a class="accordion-toggle collapsed"  ');
			var href = "#collapse_categories_"+menuObj.ID;
			if (typeof menuObj.category_url !== 'undefined') {
				href = menuObj.category_url;
			}
			liAdd = liAdd.concat('data-parent="#menu_pr" href="'+href);
			liAdd = liAdd.concat('" title="" data-original-title="Categorie_');
			liAdd = liAdd.concat(menuObj.ID);
			liAdd = liAdd.concat('" onclick="loadCatalog(');
			liAdd = liAdd.concat(menuObj.ID);
			liAdd = liAdd.concat('); return false;"><em>');
			liAdd = liAdd.concat(menuObj.section_name);
			liAdd = liAdd.concat('</em></a>');
			liAdd = liAdd.concat('</li>');
			$("#" + divElem).append(liAdd);

			var ulAdd = '<ul id="collapse_categories_';
			ulAdd = ulAdd.concat(menuObj.ID);
			ulAdd = ulAdd.concat('" class="accordion-body collapse in" style="height: auto;"></ul>');
			$("#li_category_" + menuObj.ID).append(ulAdd);

			paintFastMenu(menuObj.submenu, 'collapse_categories_' + menuObj.ID);
		} else {
			var liAdd = '<li class="accordion-group">';
			var href = "#";
			if (typeof menuObj.category_url !== 'undefined') {
				href = menuObj.category_url;
			}
			liAdd = liAdd.concat('<a href="'+href+'" onclick="loadCatalog(');
			liAdd = liAdd.concat(menuObj.ID);
			liAdd = liAdd.concat('); return false;"><em>');
			liAdd = liAdd.concat(menuObj.section_name);
			liAdd = liAdd.concat('</em></a></li>');
			$("#" + divElem).append(liAdd);
		}
	});
}

function loadCatalog(idcategory, force_load = true) {

    idcategory=idcategory || 0;

    if (typeof(slyr_cache['cat'])=='undefined') slyr_cache['cat']=[];
   	if (typeof(slyr_cache['cat'][idcategory])=='undefined' || force_load) {

        preloadShow();
        
		$.ajax({
			type: "POST",
			url: baseURL+serviceURL,
			data: { endpoint: "catalog", id: idcategory, web_url: slyr_page_home_url}
		})
		.done(function(result) {

			if (result == 0){

				loadCatalog(0);

			}else{

			    slyr_cache['cat'][idcategory]=result;
            	update_url('categories', idcategory);
				paintCatalog(result);
	            preloadHide();

	        }

		});

	} else {

        paintCatalog(slyr_cache['cat'][idcategory]);
        update_url('categories', idcategory);
        
	}

}

function update_url(type = 'categories', item_id){

	var url_to_update = '';

	if (item_id == 0){
		
		url_to_update = slyr_page_home_url;
		
	}else{

		var cache_idx = 'cat';
		var indexes = [type];

		if (type == 'products'){

			cache_idx = 'prd'

		}else{

			indexes.push("breadcrumb"); 
			
		}
		
		if (type == 'categories'){

			if (slyr_cache[cache_idx][item_id] && Object.keys(slyr_cache[cache_idx][item_id]).length > 0){

				indexes.forEach(function(index) {

					if (url_to_update == ''){

						if (slyr_cache[cache_idx][item_id][index] && slyr_cache[cache_idx][item_id][index].length > 0){

							var itemArray = slyr_cache[cache_idx][item_id][index];

							for (var i = 0; i<slyr_cache[cache_idx][item_id][index].length; i++){

								var itemObj = itemArray[i];

								if (itemObj.ID == item_id){
									
									if (typeof itemObj.category_url !== 'undefined') {

										url_to_update = itemObj.category_url;
									
									}
									
									break;

								}

							}

						}

					}

				});

			}

		}else{

			if (slyr_cache[cache_idx][item_id]){

				var itemArray = slyr_cache[cache_idx][item_id]['products'];

				for (var i = 0; i<slyr_cache[cache_idx][item_id]['products'].length; i++){

					var itemObj = itemArray[i];

					if (itemObj["orig_ID"] == item_id){
						
						if (itemObj["product_url"]){

							url_to_update = itemObj["product_url"];

						}
					
					}

				}

			}

		}
		
	}

	if (url_to_update != ''){

		window.history.pushState({}, null, url_to_update);
		
	}

}

function slyr_scrollTop () {

	var t=$('#slyr_catalogue').parent().position(true).top,
		o=$('html').scrollTop();

    if (o>t) $('html').scrollTop(t);

}
function paintCatalog(catalog) {

	var no_elements=true;

	$("#site-canvas-product").hide(0);
	$("#div_category").show(0);

	$("#div_category").empty();
	$("#breadcrumb_ul").empty();

	paintBreadcrumb(catalog.breadcrumb);

	if(catalog.categories && catalog.categories.length > 0) {
		no_elements=false;
		var categoryArray = catalog.categories;
		for (var i = 0; i<categoryArray.length; i++){ 
			var categoryObj = categoryArray[i];
			var href = "#";
			if (typeof categoryObj.category_url !== 'undefined') {
				href = categoryObj.category_url;
			}

			var divAdd = '<div class="box_elm not_thum">';
			divAdd = divAdd.concat('<div class="box_img img_on">');
			divAdd = divAdd.concat('<a href="'+href+'" onclick="loadCatalog(');
			divAdd = divAdd.concat(categoryObj.ID);
			divAdd = divAdd.concat('); return false;">');

			if(categoryObj.hasOwnProperty('section_image') && categoryObj.section_image!='' && categoryObj.section_image!=null) {
				divAdd = divAdd.concat('<img src="');
				divAdd = divAdd.concat(categoryObj.section_image);
				divAdd = divAdd.concat('">');
			} else {
				divAdd = divAdd.concat('<img src="'+baseURL+'images/placeholder.gif">');
			}

			divAdd = divAdd.concat('</a></div><div class="box_inf">');
			divAdd = divAdd.concat('<h7><a class="section" href="'+href+'" onclick="loadCatalog(');
			divAdd = divAdd.concat(categoryObj.ID);
			divAdd = divAdd.concat('); return false;">');
			divAdd = divAdd.concat(categoryObj.section_name);
			divAdd = divAdd.concat('</a></h7></div></div>');
			$("#div_category").append(divAdd);
		}
		$("#dropdown_ul").empty();
	} 
	if(catalog.products && catalog.products.length > 0) {
			
		no_elements=false;
		var productArray = catalog.products;
		for (var i = 0; i<productArray.length; i++){ 
			var productObj = productArray[i];
			var href = "#";
			if (typeof productObj.product_url !== 'undefined') {
				href = productObj.product_url;
			}

			var divAdd = '<div class="box_elm not_thum ">';
			divAdd = divAdd.concat('<div class="box_img img_on">');
			divAdd = divAdd.concat('<a href="'+href+'" onclick="loadProduct(');
			divAdd = divAdd.concat(productObj.ID);
			divAdd = divAdd.concat('); return false;">');

			if(productObj.hasOwnProperty('product_image') && productObj.product_image!="") {
				divAdd = divAdd.concat('<img src="');
				divAdd = divAdd.concat(productObj.product_image);
				divAdd = divAdd.concat('">');
			} else {
				divAdd = divAdd.concat('<img src="'+baseURL+'images/placeholder.gif">');
			}

			divAdd = divAdd.concat('</a></div><div class="box_inf">');
			divAdd = divAdd.concat('<h7><a href="'+href+'" class="product" onclick="loadProduct(');
			divAdd = divAdd.concat(productObj.ID);
			divAdd = divAdd.concat('); return false;">');
			if (productObj.product_name) {
				divAdd = divAdd.concat(productObj.product_name);
			} else {
				divAdd = divAdd.concat("Product undefined");
			}
			divAdd = divAdd.concat('</a></h7></div></div>');
			$("#div_category").append(divAdd);
		}
		
		last_crumb    =(catalog.breadcrumb != null) ? catalog.breadcrumb.length -1 : 0;
		lastIdCategory=(last_crumb>=0 && typeof(catalog.breadcrumb[last_crumb])!='undefined') ?
							((catalog.breadcrumb != null) ? catalog.breadcrumb[last_crumb].ID : 0) : 0;
	} 

	if(no_elements) {
		var divAdd = '<div class="message">';
	    ivAdd = divAdd.concat('<h5>There are no products inside this category.</h5>');
		divAdd = divAdd.concat('</div>');
		$("#div_category").append(divAdd);
		$("#dropdown_ul").empty();
	}

    slyr_scrollTop();
}

function paintBreadcrumb(breadcrumbArray) {
	
	var liAdd = '<li><a href="#" onclick="loadCatalog(0); return false;">Start</a></li>';
	$("#breadcrumb_ul").append(liAdd);

	if (breadcrumbArray != null) {
		var last = breadcrumbArray.length-1;

		for (var i = 0; i<breadcrumbArray.length; i++){ 

			var breadcrumb = breadcrumbArray[i];
			var liAdd = '';
			var href = "#";
			if (typeof breadcrumb.category_url !== 'undefined') {
				href = breadcrumb.category_url;
			}

			//si es el ultimo elemento...
			if (i == last) {
				liAdd = liAdd.concat('<li class="active"><a href="'+href+'" onclick="loadCatalog(');
			} else {
				liAdd = liAdd.concat('<li><a href="'+href+'" onclick="loadCatalog(');
			}
			liAdd = liAdd.concat(breadcrumb.ID);
			liAdd = liAdd.concat('); return false;">')
			liAdd = liAdd.concat(breadcrumb.section_name);
			liAdd = liAdd.concat('</a></li>');

			$("#breadcrumb_ul").append(liAdd);
		}
	}
}

function loadProduct(idproduct) {

	if (typeof(slyr_cache['prd'])=='undefined'){
    
    	slyr_cache['prd']=[];
    
    } 

    if (typeof(slyr_cache['prd'][idproduct])=='undefined') {
	
	    preloadShow();

		$.ajax({
			type: 'POST',
			url: baseURL+serviceURL,
			data: { endpoint: 'products', id: idproduct, web_url: slyr_page_home_url}
		})
		.done(function(result) {

			if (result == 0){
	
				loadCatalog(0);
	
			}else{

	            slyr_cache['prd'][idproduct]=result;
	        	update_url('products', idproduct);
				paintProduct(result);
	            preloadHide();
	
			}
	
		});

	} else {

	    paintProduct(slyr_cache['prd'][idproduct]);
        update_url('products', idproduct);
	
	}

}

function backCatalog(catalog_id) {

	loadCatalog(catalog_id);
	$('#site-canvas-product').hide(0);
	$('#div_category').show(0);

}

function paintProduct(product) {

	$("#breadcrumb_ul").empty();
	paintBreadcrumb(product.breadcrumb);

	if (product.products.length) {
	
        $('#site-canvas-product').show(0);
		$('#div_category')       .hide(0);

		$('#gallery').unbind().empty();

		$('<div>', {id:'div_image_preview', class:'image-preview'}).appendTo('#gallery');

		$.each(product.products, function(key, options) {
	
			if (!options.hasOwnProperty('product_description') || options.product_description == '' || options.product_description == null ){

				options.product_description = '';

			}

            if (options.product_description.length && !options.product_description.match(/<\w+[^>]*>/g)) {

                options.product_description=options.product_description.replace(/(\n\r|\r\n|\n)/, '<br>');
			}

			$('#h_product_name')       .empty().append(options.product_name);
			$('#p_product_description').empty().append(options.product_description);
			$('#p_characteristics')    .empty().append(options.characteristics);
			$('#p_formats')    .empty().append(options.formats);
			$('#backcatalog').attr("onclick","backCatalog('"+options.catalogue_id+"'); return false;");

			if (options.hasOwnProperty('product_image') && options.product_image!='' && options.product_image!=null) {

				var img_fmt = options.IMG_FMT, kbase='';

				$.each(options.product_image, function(k, images) {

					if (!kbase) kbase=k;

					if (typeof(images[img_fmt])!='undefined' && images[img_fmt]!='') {

						var objShadowbox = $('<a>', {id: 'apreview', rel:'shadowbox', href:images[img_fmt] });

						objShadowbox.append($('<img>', {id:'preview', class: 'vw_detl', src: images.THM}));
						$('#div_image_preview').append(objShadowbox);

					} else {

                        $('#div_image_preview').append($('<img>', {id:'preview', src: images.THM}));
					}

					$('<ul>', {id:'carousel', class:'slide-list'}).appendTo('#gallery');

					return false;
				});

                imgs=0; for (i in options.product_image) { if (options.product_image.hasOwnProperty(i)) imgs++; }

				if (imgs>1) {

                    $('#carousel').unbind(); var imo=1;

					$.each(options.product_image, function(k, imgs) {

						$('#carousel').append('<li  class="imo'+imo+(k==kbase ? ' current' : '')+'"><a href="#" onclick="return changeImage('+
											  imo+',\''+imgs.THM+'\',\''+imgs[img_fmt]+'\')"><img src="'+imgs.TH+'"/></a></li>');

                        imo++;
					});
				}

			} else {

				$('#div_image_preview').append($('<img>', {id:'preview', src: (baseURL+'images/placeholder.gif')}))
				$('<ul>', {id:'carousel', class:'elastislide-list'}).appendTo('#gallery');
			}

		});

        Shadowbox.clearCache();
		Shadowbox.setup();

        slyr_scrollTop();
	
	}

}

function changeImage (k, ih, ib) {

	$('#preview') .attr('src',  ih);
    $('#apreview').attr('href', ib);

	$('#carousel li').removeClass('current');

	$('#carousel .imo'+k).addClass('current');

	Shadowbox.clearCache();
	Shadowbox.setup();

	return false;
}

function check_slyr_page_params(){

	var exploded_pathname = window.location.pathname.split('/');
	var url_has_item = false;

	if (exploded_pathname.length > 0){

		if (exploded_pathname[2] == relativeUrl){

			if (typeof exploded_pathname[3] !== 'undefined' && exploded_pathname[3] != ''){

				var type = exploded_pathname[3].substr(0, 1);
				if (type == 'c'){

					url_has_item = true;
					var item_id = exploded_pathname[3].substr(1, exploded_pathname[3].length);
					loadCatalog(item_id);

				}else if (type == 'p'){

					url_has_item = true;
					var item_id = exploded_pathname[3].substr(1, exploded_pathname[3].length);
					loadProduct(item_id);

				}

			}

		}

	}

	if (!url_has_item){

		loadCatalog(0);

	}

}

function loadVars(){
	

	if (typeof plugins_url !== 'undefined' && typeof plugin_name_dir !== 'undefined'){

		baseURL = plugins_url+'/'+plugin_name_dir+'/';

		$('.slyr_cat_prld').css('background', 'url('+baseURL+'images/loading.gif) center center no-repeat');

	}

}
$(document).ready(function(){

	loadVars();

	if (typeof baseURL !== 'undefined' && baseURL != '') {

		loadFastMenu();
	
		if (preloaded_info == 0){
	
			check_slyr_page_params();
		
		}else{

			if (preloaded_url != ''){

				window.history.pushState({}, null, preloaded_url);

			}

		}
	
		Shadowbox.init();

		$( "#sl_search" ).submit(function( event ) {
			
			var search_value = $("#sl_search_value").val();

			if (search_value != ''){

				$.ajax({
					type: 'POST',
					url: baseURL+serviceURL,
					data: { endpoint: 'search_item', search_value: search_value, web_url: slyr_page_home_url}
				})
				.done(function(result) {

					if (result == 0){
	
						$("#sl_search_value").val('');
						loadCatalog(0);
	
					}else{

						if (result.type == 'c'){

				    		loadCatalog(result.id);

						}else if (result.type == 'p'){

							loadProduct(result.id);

						}
	
					}

				});

			}
	
		  	event.preventDefault();
	
		});

	}

});

