var resources= {
  'en-US': { 
    translation: {
      "catalog": {
        "panel_principal": "Start",
        "catalogo": "Start",
        "navegacion": "Toggle navigation",
        "logo": "Logo"
      },
      "notfound": {
        "mensaje": "There are no products inside this category."
      },
      "howto":{
        "title": "How To Start"        
      }
    }
  },
  'es-ES':{
    translation: {
      "catalog": {
          "panel_principal": "Inicio",
          "catalogo": "Inicio",
          "navegacion": "Flechas de navegación",
          "logo": "Logotipo"
      },
      "notfound": {
        "mensaje": "No existen productos dentro de esta categoría."
      },
      "howto":{
        "title": "¿Cómo comenzar?"        
      }
    }
  }

};




i18n.init(
  {
    fallbackLng: 'en-US',
    detectLngQS: 'lng',
    resStore: resources
  },
  function(t) {
    $("title, em, span, a, h4, h6").i18n();

    $(function() {
    	$('#title_dashboard').attr("data-original-title", t("catalog.panel_principal"));
      $('#title_catalog').attr("data-original-title", t("catalog.catalogo"));
    });
});


