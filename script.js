/**
 * DokuWiki Ptolemy (Syntax)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Brad Osborne <bosborne.csm@gmail.com>, Yuuki
 * 
 * Integrated with (and inspired by) the Struct plugin by Andreas Bohr.
 * 
 */
jQuery(function(){
    /* DOKUWIKI:include lib/leaflet/leaflet.js */
    /* DOKUWIKI:include lib/fontawesome/js/all.js */
    /* DOKUWIKI:include lib/geoman/leaflet-geoman.min.js */

    window.PTOLEMY_DEBUG = false;

    function init(){
        jQuery(".ptolemy-map").each(function(index){
            jQuery(this).attr("id","ptolemy-" + index);
            jQuery(this).attr("data-ptolemy-id",index);
            createMap(this,ptolemyData[index]);
        });
    }

    jQuery(init);

    function createMap(element,mapData){
        var mapMarkerLayers = [];
        var mapMarkerLayerVisibility = [];
        var controlLayers = [];

        let bounds = [[0,-0], [mapData.mapConfiguration.mapHeight, mapData.mapConfiguration.mapWidth]];
        let maxBounds = [[-400,-400], [mapData.mapConfiguration.mapHeight+400, mapData.mapConfiguration.mapWidth+400]];

        map = L.map(jQuery(element).attr("id"), {
            crs: L.CRS.Simple,
            minZoom: mapData.mapConfiguration.minZoom,
            maxZoom: mapData.mapConfiguration.maxZoom,
            zoom: jQuery(element).data("initial-zoom"),
            maxBounds: maxBounds,
            maxBoundsViscosity: .5,
            center: [mapData.mapConfiguration.mapHeight/2,mapData.mapConfiguration.mapWidth/2]
        });

        map.setView([jQuery(element).data("initial-y"), jQuery(element).data("initial-x")]);
        jQuery(element).attr("current-zoom",map.getZoom());

        let image = L.imageOverlay(mapData.mapConfiguration.filename, bounds).addTo(map);

        jQuery(element).append("<div class='ptolemy-loading'><i class='fas fa-satellite-dish'></i></div>");

        image.on('load', function(){
            buildMapLayers();
            createMapControls();
            jQuery(".ptolemy-loading").remove();
        });

        function buildMapLayers(){
            if(window.PTOLEMY_DEBUG){
                console.log("Building map layers");
                console.log(mapData);
            }
            if (map && mapData){
                controlLayers = L.control.layers( null, null, {
                    position: "bottomright",
                    collapsed: false
                }).addTo(map);

                if(mapData.hasOwnProperty('markerLayers') && Array.isArray(mapData.markerLayers)){
                    mapData.markerLayers.forEach((layerElement, layerIndex) =>{
                        mapMarkerLayers[layerIndex] = new L.LayerGroup().addTo(map);
                        
                        if(layerElement.hasOwnProperty('objects') && Array.isArray(layerElement.objects)){
                            layerElement.objects.forEach((marker) =>{
                                createMapObject(marker, layerIndex);
                            });
                        }

                        mapMarkerLayerVisibility[layerIndex] = layerElement.layerVisibility.split(',').map(Number);

                        if(layerElement.layerName && layerElement.layerName.length > 0){
                            controlLayers.addOverlay(mapMarkerLayers[layerIndex], layerElement.layerName);
                        }
                    });
                    filterMapLayers();
                }else{
                    toast("No map data to display", "error");
                    console.log(mapData);
                }

            }else{
                toast("Map or map data is missing or invalid", "error");
            }
        }

        function filterMapLayers(){
            mapMarkerLayerVisibility.forEach((layerVisibility, index) => {
                if(layerVisibility.includes(map.getZoom())){
                    if (!map.hasLayer(mapMarkerLayers[index])){
                        mapMarkerLayers[index].addTo(map);
                    }
                }else{
                    if (map.hasLayer(mapMarkerLayers[index])){
                        mapMarkerLayers[index].removeFrom(map);
                    }
                }

            });
        }        

        function createMapObject(objectData, layerIndex){            
            let mapObject = null;
            switch(objectData.geometry.type){
                case "Label":
                    mapObject = createMapLabel(objectData);
                    break;
                case "Point":
                    mapObject = createMapMarker(objectData);
                    break;
                case "LineString":
                    mapObject = createMapLine(objectData);
                    break;
                case "Polygon":
                    mapObject = createMapPolygon(objectData);
                    break;
            }
            if (mapObject != null){
                mapObject.addTo(mapMarkerLayers[layerIndex]);
            }else{
                toast("Unable to add to map", "error");
                console.log("Unable to add Map Object.");
            }
        }
        
        function onEachFeature(feature, layer) {
            let markerIcon = feature.iconUrl ? `<img class="markerIcon" src="${feature.iconUrl}"></img>` : "";

            const popupContent = `<div class="markerPopupWrapper">
            <div class="markerPopupIconWrapper">
                ${markerIcon}
            </div>
            <div class="markerPopupTextWrapper">
                <span class="popupTitle">${feature.name}</span><br>
                ${feature.tooltipContent}
                ${(feature.tooltipLink && feature.tooltipLink.length > 0) ? `<br><a href="${feature.tooltipLink}" target="_blank" class="popupLink"><i class="fa fa-book"></i><span style="padding-left:3px">View on  Wiki</span></a>` : ''}
            </div>
            </div>`;

            layer.bindPopup(popupContent);
            layer.on('pm:edit', function(e){
                if(window.PTOLEMY_DEBUG){
                    console.log("Geoman edit event");
                    console.log(e);
                }
                e.layer.closePopup();
                e.layer.unbindPopup();
                e.layer.bindPopup(createGeomanPopup(e));
            });
            layer.on('pm:cut', function(e){
                if(window.PTOLEMY_DEBUG){
                    console.log("Geoman cut event");
                    console.log(e);
                }
                e.layer.closePopup();
                e.layer.unbindPopup();
                e.layer.bindPopup(createGeomanPopup(e));
            });

            if (feature.hasOwnProperty('showLabel') && feature.showLabel === "Yes"){
                if (!feature.hasOwnProperty('tooltipDirection') || !feature.tooltipDirection == "center"){
                    feature.tooltipDirection = "auto";
                }
                let labelHtml = `<span style="color:${feature.color};">${feature.name}</span>`;

                let labelClasses =  "ptolemy-map-label";
                if(feature.priority){
                    labelClasses += ` ptolemy-${feature.priority.replaceAll(' ','-').toLowerCase()}`;
                }

                layer.bindTooltip(labelHtml, {permanent: true, direction: feature.tooltipDirection, className: labelClasses, offset: [0,0]});
            }
        }

        function createMapMarker(objectData){
            let mapMarkerObject = null;
            let anchorCoords = [];

            if (objectData.markerAnchor == "Center Center"){
                anchorCoords[0] = objectData.iconDimensions[0]/2;
                anchorCoords[1] = objectData.iconDimensions[1]/2
            }else if (objectData.markerAnchor == "Bottom Center"){
                anchorCoords[0] = objectData.iconDimensions[0]/2;   
                anchorCoords[1] = objectData.iconDimensions[1];
            }else{
                anchorCoords[0] = 0;   
                anchorCoords[1] = objectData.iconDimensions[1];
            }


            let markerClasses =  "ptolemy-map-marker";
            if(objectData.priority){
                markerClasses += ` ptolemy-${objectData.priority.replaceAll(' ','-').toLowerCase()}`;
            }

            if (objectData.hasOwnProperty('iconUrl') && objectData.iconUrl.length > 0){
                mapMarkerObject = L.geoJSON(objectData, {
                    pointToLayer: function (feature, latlng) {
                        let divIcon = L.divIcon({
                            iconSize: [objectData.iconDimensions[0],objectData.iconDimensions[1]],
                            iconAnchor: [anchorCoords[0],anchorCoords[1]],
                            html:`<img class='${markerClasses}' src='${objectData.iconUrl}'></img>`,
                            className: ''
                        });
                        return L.marker(latlng, {icon: divIcon});
                    },
                    onEachFeature: onEachFeature
                });
            }

            return mapMarkerObject;
        }

        function createMapLine(objectData){
            let mapLineObject = null;

            mapLineObject = {
                "type": "LineString",
                "coordinates": objectData.geometry.coordinates
            };

            mapLineObject = L.geoJSON(objectData, {
                onEachFeature: onEachFeature,
                style: {
                    weight:2,
                    color: objectData.color
                }
            });

            return mapLineObject;
        }       

        function createMapPolygon(objectData){
            let mapPolygonObject = null;

            mapPolygonObject = {
                "type": "Feature",
                "geometry": objectData.geometry
            };

            mapPolygonObject = L.geoJSON(objectData, {
                onEachFeature: onEachFeature,
                style: {
                    weight:2,
                    color: objectData.color
                }
            });

            return mapPolygonObject;
        }

        function createMapLabel(objectData){
            let mapLabelObject = null;
            objectData.geometry.type = "Point";
            objectData.showLabel = "Yes";

            if (objectData.hasOwnProperty('name') && objectData.name.length > 0){
                mapLabelObject = L.geoJSON(objectData, {
                    pointToLayer: function (feature, latlng) {
                        let divIcon = L.divIcon({
                            iconSize: [0,0],
                            className: `ptolemy-map-label ptolemy-map-label-${objectData.labelClass}`
                        });
                        return L.marker(latlng, {icon: divIcon});
                    },
                    onEachFeature: onEachFeature
                });
            }

          return mapLabelObject;
        }

        function createMapControls(){
            let geomanEditType;

            let ptolemyMapCommands = `<div class='leaflet-control-container ptolemy-map-commands'>
                <div class="leaflet-top leaflet-left">
                    <div class="leaflet-bar leaflet-control">
                        <a class="panMap" href="#" title="Pan the Map" role="button" aria-label="Pan" aria-disabled="true"><span aria-hidden="true"><i class='fas fa-hand-paper'></i></span></a>
                        <a class="expand leaflet-inactive" href="#" title="Expand/shrink the map" role="button" aria-label="CopyCoordinates" aria-disabled="false"><span aria-hidden="true"><i class="fas fa-expand-arrows-alt"></i></span></a>
                        <a class="centerMap leaflet-inactive" href="#" title="Center the map" role="button" aria-label="CenterMap" aria-disabled="false"><span aria-hidden="true"><i class="fa-solid fa-arrows-to-eye"></i></span></a>
                    </div>
                </div>
            </div>
            `;

            jQuery(element).append(ptolemyMapCommands);

            /* TODO We may be able to leverage Geoman's custom toolbar here and simplify the code. */
            jQuery(element).find(".ptolemy-map-commands a").on("click", function(event){
                event.preventDefault();
                event.stopPropagation();
                switch (event.currentTarget.classList[0]){
                    case "panMap":
                        jQuery(event.currentTarget).removeClass('leaflet-inactive');
                        jQuery(event.currentTarget).siblings().addClass('leaflet-inactive');
                        jQuery(element).removeClass('coordToClipboardMode');
                        break;
                    case "expand":
                        if (jQuery(element).hasClass("expand")){
                            jQuery(element).removeClass("expand");
                            jQuery(event.currentTarget).removeClass("leaflet-expand-active");
                            jQuery(event.currentTarget).html('<span aria-hidden="true"><i class="fas fa-expand-arrows-alt"></i></span>');
                            jQuery(".ptolemy-underlay").remove();
                        }else{
                            jQuery(element).addClass("expand");
                            jQuery(event.currentTarget).addClass("leaflet-expand-active");
                            jQuery(event.currentTarget).html('<span aria-hidden="true"><i class="fas fa-compress-arrows-alt"></i></span>');
                            jQuery("body").append("<div class='ptolemy-underlay'></div>");
                            jQuery(".ptolemy-underlay").click(function(){
                                    jQuery(element).removeClass("expand");
                                    jQuery(element).find('.ptolemy-map-commands a.expand').removeClass("leaflet-expand-active");
                                    jQuery(".ptolemy-underlay").remove();
                                    jQuery(element).find('.ptolemy-map-commands a.expand').html('<span aria-hidden="true"><i class="fas fa-expand-arrows-alt"></i></span>');
                                    map.invalidateSize();
                                    map.setView([jQuery(element).data("initial-y"), jQuery(element).data("initial-x")],jQuery(element).data("initial-zoom"));
                            });
                        }
                        map.invalidateSize();
                        map.setView(map.getCenter(), map.getZoom());
                        break;
                    case "centerMap":
                        map.setView(L.latLng(mapData.mapConfiguration.mapHeight/2,mapData.mapConfiguration.mapWidth/2), map.getZoom());
                        break;
                }
            });

            if (!map.pm) {
                L.PM.reInitLayer(map);
                console.log("Geoman wasn't initialized. Trying again.");
            }

            /* leaflet Geoman options */
            map.pm.addControls({
                position: 'topleft',
                drawCircle: false,
                drawCircleMarker: false,
                drawRectangle: false,
                drawText: true,
                dragMode: false,
                drawPolyline: true,
                drawPolygon: true,
                cutPolygon: true,
                removalMode: true,
                rotateMode: false
            });

            map.pm.setGlobalOptions({
                allowSelfIntersection: false
              });

            /* hacky fix for getting rid of the draw circle button, since the above doesn't do the trick for some reason. */

            jQuery('.leaflet-pm-icon-circle-marker').closest('.button-container').remove();

            map.on('pm:create', (e)=>{
                if(window.PTOLEMY_DEBUG){
                    console.log("Geoman create event");
                    console.log(e);
                }

                if (e.layer.getPopup != null){
                    e.layer.bindPopup(createGeomanPopup(e));
                }
                e.layer.on('pm:edit', function(e){
                    e.layer.closePopup();
                    e.layer.unbindPopup();
                    e.layer.bindPopup(createGeomanPopup(e));
                });
                e.layer.on('pm:cut', function(e){
                    e.layer.closePopup();
                    e.layer.unbindPopup();
                    e.layer.bindPopup(createGeomanPopup(e));
                });

                if(window.PTOLEMY_DEBUG){
                    console.log("Create/Bind completed successfully.");
                }
            });      

            map.on('pm:drawstart', (e)=>{
                if(window.PTOLEMY_DEBUG){
                    console.log("Geoman draw event");
                    console.log(e);
                }
                geomanEditActive = true;
                geomanEditType = e.shape;
            });

            map.on('pm:drawend', (e)=>{
                if(window.PTOLEMY_DEBUG){
                    console.log("Geoman draw event");
                    console.log(e);
                }
            });

            map.on('contextmenu', (e)=>{
                if(window.PTOLEMY_DEBUG){
                    console.log("Leaflet context menu event");
                    console.log(e);
                }
            });

            map.on("zoomend", function (e) { 
                if(window.PTOLEMY_DEBUG){
                    console.log("Leaflet zoom end event");
                    console.log(e);
                }
                jQuery(e.target._container).attr("current-zoom",map.getZoom());
                filterMapLayers();
            });
        }

        /* create the popup element and handle its bindings */
        function createGeomanPopup(e){
            if(window.PTOLEMY_DEBUG){
                console.log(`Creating popup for shape of type ${e.shape}.`);
                console.log(e);
            }
            let coordsString = "";
            let geomanPopup = L.DomUtil.create('div', 'geoman-popup');
            switch(e.shape){
                case "Text":
                    coordsString = simplifyLatlngToString(e.layer._latlng);
                    break;
                case "Marker":
                    coordsString = simplifyLatlngToString(e.layer._latlng);
                    break;
                case "Line":
                    coordsString = simplifyLatlngsToString(e.layer._latlngs);
                    break;
                case "Polygon":
                    coordsString = simplifyLatlngArrayToString(e.layer._latlngs);
                    break;
                case "Cut":
                    coordsString = simplifyLatlngArrayToString(e.layer._latlngs);
                    break;
            }

            const popupHtml = `
                <span class="geoman-title">${e.shape}</span>
                <span>
                    <span class="geoman-label">Coords</span>
                    <span class="geoman-data">${coordsString}</span>
                    <a href="#" class="geoman-copy-button"><i class="fa-regular fa-copy"></i></a><br>
                </span>
                <span class="geoman-tip">Add or update this in the Struct for the relevant page to see it on the live map.</span>`;
            geomanPopup.innerHTML = popupHtml;

            if(window.PTOLEMY_DEBUG){
                console.log(`Popup text created and assigned.`);
                console.log(popupHtml);
            }

            jQuery('a.geoman-copy-button', geomanPopup).on('click', function(e){
                navigator.clipboard.writeText(jQuery(e.currentTarget).siblings(".geoman-data").text());
                toast(`Copied to clipboard.`);
            });

            if(window.PTOLEMY_DEBUG){
                console.log(`Popup click event successfully attached. Returning popup div element.`);
            }

            return geomanPopup;
        }

        function simplifyLatlngArrayToString(latlngArray){
            return "[" + latlngArray.map((subarray) => simplifyLatlngsToString(subarray)).join('|') + "]";
        }

        function simplifyLatlngsToString(latlngs){
            return latlngs.map((object) => simplifyLatlngToString(object)).join(';');
        }

        function simplifyLatlngToString(latlng){
            let y,x;
            y = latlng.lng > 0 ? Math.floor(latlng.lng) : 0;
            x = latlng.lat > 0 ? Math.floor(latlng.lat) : 0;
            if (latlng.lng > mapData.mapConfiguration.mapHeight) y = mapData.mapConfiguration.mapHeight;
            if (latlng.lat > mapData.mapConfiguration.mapWidth) x = mapData.mapConfiguration.mapWidth;
            return `${y}, ${x}`;
        }

        function toast(message, optClass){
            jQuery(element).append(`<div class='ptolemy-toast ${optClass}'>${message}</div>`);
            setTimeout(function(){
                jQuery('.ptolemy-toast').fadeOut(800, function(){
                    jQuery(this).remove();
                });
            }, 2000);
        }
    }
});