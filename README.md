# Ptolemy

Ptolemy is a Dokuwiki plugin that lets you turn data from the [Struct plugin](https://www.dokuwiki.org/plugin:struct) into interactive maps.

## Setup

This plugin requires the SQLite and Struct plugins to work, as well as a few Struct schemas assigned to namespaces in your wiki. Once you've installed the plugin (as easy as copying the plugin into your wiki's /lib/plugins/ folder), create the following schemas. These are the default schema names, but you can use a different name if you so choose.

#### Schema - "Maps"

The "Maps" schema defines all the crucial information about a Map, including where it looks for Locations.

| Field Name | Configuration | Type | Notes |
| ---------- | ------------- | ---- | ----- |
| map_name | label: "Map Name" | text | |
| map_namespace | label: "Locations Namespaces" | text | If you want to name your Location schema or namespace something else, you can change the label here. Accepts a comma delineated list of namespaces. |
| map_zoom_minmax | label: "Map Minimum and Maximum Zoom" // hint: "-1,1" | text | Depending on your image size, you might want more control over how much your users can zoom in/out. |
| map_media | label: "Map Media" | media | The file that your map will use. Uses dokuwiki's media system, so you can upload and manage your map images easily. |
| show_own_markers_only | label: "Show only markers that are children of this map?" // Values: "Yes, No" | checkbox | By default, maps will show all markers. This sets it the map to only show markers who have identified it as a parent. |

#### Schema - "Locations"

The "Locations" schema defines information needed to show, or customize the appearance of, content on your Map. 

| Field Name | Configuration | Type | Notes |
| ---------- | ------------- | ---- | ----- |
| display_name | label: "Location Name" | text | Locations without a name won't have a tooltip. |
| coords | label: "Coordinates" | text | Users can get this by using the 'Marker' icon from the Map's menu, then paste it in. |
| visibility | label: "Importance" // values: "Landmark, Major RP Hub, Minor RP Location, Trivial" | dropdown | The values for this field will determine what zoom levels your marker is shown at. |
| marker_image | label: "Marker Image" | media | Users will use this to set the image they want to show on the map for their locations. NOTE- Right now, there are no protections against images being too large, small, etc. You'll need to manage this yourself. |
| tooltip_content | label: "Tooltip Content" | text | Lets your users enter in text that will show in the tooltip for this marker. The tooltip only shows basic text with no formatting considerations. |
| show_label | label: "Show label?" // values: "Yes" | checkbox |
| parent_map | label: "Associated Map" // schema: maps // field: map_name | lookup | Makes a dropdown list of all the maps you make. |
| marker_anchor | label: "What part of your image should be on the coordinates?" // values: "Bottom Left, Center Center, Bottom Center" | dropdown | Where to anchor the marker image. Flags and others benefit from a bottom-left anchor, so that's the default for now. |
| color | label: "Color" | color | Lets users select a color for their lines/regions. Defaults to a soft blue if empty or this field is missing. |

#### Assigning Schemas

Once your Schemas have been created, assign them to appropriate namespaces. 

I recommend that you use "maps:**" for Maps. 

You can assign "locations" to any namespace that contains pages you would like to add to your map.

## Creating your first map

Once your schemas are setup, creating your first map requires you to create a page within the namespace you assigned to your maps schema.  When you edit this page, you will be prompted to fill out the struct data.  The most important field is map_media, which is where you should add your base map image.

## Syntax

Once you've created a map, adding it to a page is easy.

`~~MAP|*YOUR_MAPS_NAMESPACE:YOUR_MAP'S_PAGE_NAME*|*COORD_X,COORD_Y*|*INITIAL_ZOOM*~~`

example:

`~~MAP|maps:map1|150,150|1~~`

#### YOUR_MAPS_NAMESPACE:YOUR_MAP'S_PAGE_NAME

The path to the map page you made. Needs to have the "Maps" schema from above and data set to it. Similar to inter-wiki links, you only need the part after "id=" in the URL.

This lets you place a map you've defined anywhere in your wiki. Each time you use the map, you can center and zoom in on something of interest.

#### COORD_X, COORD_Y

The initial coordinates for your map to center on.

#### INITIAL_ZOOM

A zoom level equal to or between your minimum and maximum zooms. If a user specifies a number outside what you defined on the map, it'll stop at the appropriate minimum or maximum zoom.

## Adding Content to your Maps

You've got Schemas and a Map. Now what?

#### Adding Locations

For pages that have the "Locations" schema and a Map looking in their namespace, just fill out their Struct.

## Notes and Considerations

The data that drives your maps and locations are cached automatically by dokuwiki. If you make a change and don't see it reflected on your map, it may be because of the cache. You can add `~~NOCACHE~~` to the top of a page's content to disable the page from caching, as well as disabling/clearing your browser's cache.

Once you've got things the way you want them, it's a good idea to remove the NOCACHE syntax from your page so that it loads quickly.

## Troubleshooting

#### Map Won't Zoom In / Out

Most likely, your min and max zooms are backwards. The minimum zoom defines how far 'out' you can zoom and is typically a negative number, such as "-2". The maximum zoom is how far you can zoom 'in' and is usually positive, such as "2".

## Docker

It is possible to run this plugin from within Docker, which is useful for testing in a consistent environment.

First build the docker container using this command:

```
docker build -t ptolemy .
```

Then run the container using this command:

```
docker run -p 8080:8080 ptolemy
```

You can now login by navigating to http://localhost:8080

You can login with the default user with username `user` and password `bitnami1`.
