(function (blocks, element, components, i18n, editor) {
  "use strict";

  const { registerBlockType } = blocks;
  const { createElement: el, Fragment } = element;
  const {
    PanelBody,
    SelectControl,
    ToggleControl,
    RangeControl,
    RadioControl,
    __experimentalNumberControl: NumberControl,
  } = components;
  const { __ } = i18n;
  const { InspectorControls } = editor;

  // Block registration
  registerBlockType("authdocs/document-grid", {
    title: authdocs_block.title,
    description: authdocs_block.description,
    icon: authdocs_block.icon,
    category: "widgets",
    keywords: [
      __("documents", "authdocs"),
      __("grid", "authdocs"),
      __("authdocs", "authdocs"),
    ],
    attributes: {
      limit: {
        type: "number",
        default: 12,
      },
      loadMoreLimit: {
        type: "number",
        default: 12,
      },
      paginationType: {
        type: "string",
        default: "classic",
      },
      featuredImage: {
        type: "boolean",
        default: true,
      },
      paginationStyle: {
        type: "string",
        default: "classic",
      },
      restriction: {
        type: "string",
        default: "all",
      },
      showDescription: {
        type: "boolean",
        default: true,
      },
      showDate: {
        type: "boolean",
        default: true,
      },
      orderby: {
        type: "string",
        default: "date",
      },
      order: {
        type: "string",
        default: "DESC",
      },
    },

    edit: function (props) {
      const { attributes, setAttributes } = props;
      const {
        limit,
        loadMoreLimit,
        paginationType,
        featuredImage,
        paginationStyle,
        restriction,
        showDescription,
        showDate,
        orderby,
        order,
      } = attributes;

      // Auto-calculate columns based on limit
      const calculateColumns = (documentsPerPage) => {
        if (documentsPerPage <= 4) return 2;
        if (documentsPerPage <= 9) return 3;
        if (documentsPerPage <= 16) return 4;
        if (documentsPerPage <= 25) return 5;
        return 6;
      };

      const columns = calculateColumns(limit);

      // Handle pagination style changes
      const handlePaginationStyleChange = (value) => {
        setAttributes({ paginationStyle: value });

        // Auto-adjust pagination type based on style
        if (value === "load_more") {
          setAttributes({ paginationType: "ajax" });
        } else if (value === "classic") {
          setAttributes({ paginationType: "classic" });
        }
      };

      return el(
        Fragment,
        {},
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            { title: __("Grid Settings", "authdocs"), initialOpen: true },
            el(NumberControl, {
              label: __("Documents per page", "authdocs"),
              value: limit,
              onChange: (value) =>
                setAttributes({ limit: parseInt(value) || 12 }),
              min: 1,
              max: 100,
              help: __("Number of documents to display per page", "authdocs"),
            }),
            el(NumberControl, {
              label: __("Load more limit", "authdocs"),
              value: loadMoreLimit,
              onChange: (value) =>
                setAttributes({ loadMoreLimit: parseInt(value) || 12 }),
              min: 1,
              max: 50,
              help: __(
                'Number of additional documents to load when "Load More" is clicked',
                "authdocs"
              ),
            })
          ),

          el(
            PanelBody,
            {
              title: __("Pagination Settings", "authdocs"),
              initialOpen: false,
            },
            el(SelectControl, {
              label: __("Pagination Style", "authdocs"),
              value: paginationStyle,
              options: [
                {
                  label: __("Classic Pagination", "authdocs"),
                  value: "classic",
                },
                {
                  label: __("Load More Button", "authdocs"),
                  value: "load_more",
                },
                { label: __("No Pagination", "authdocs"), value: "none" },
              ],
              onChange: handlePaginationStyleChange,
              help: __("Choose how pagination is displayed", "authdocs"),
            }),
            paginationStyle === "classic" &&
              el(RadioControl, {
                label: __("Pagination Type", "authdocs"),
                selected: paginationType,
                options: [
                  {
                    label: __("Classic (Page Reload)", "authdocs"),
                    value: "classic",
                  },
                  { label: __("AJAX (No Reload)", "authdocs"), value: "ajax" },
                ],
                onChange: (value) => setAttributes({ paginationType: value }),
                help: __("How pagination navigation works", "authdocs"),
              })
          ),

          el(
            PanelBody,
            { title: __("Display Settings", "authdocs"), initialOpen: false },
            el(ToggleControl, {
              label: __("Show Featured Images", "authdocs"),
              checked: featuredImage,
              onChange: (value) => setAttributes({ featuredImage: value }),
              help: __(
                "Display featured images as card backgrounds",
                "authdocs"
              ),
            }),
            el(ToggleControl, {
              label: __("Show Descriptions", "authdocs"),
              checked: showDescription,
              onChange: (value) => setAttributes({ showDescription: value }),
              help: __("Display document descriptions", "authdocs"),
            }),
            el(ToggleControl, {
              label: __("Show Dates", "authdocs"),
              checked: showDate,
              onChange: (value) => setAttributes({ showDate: value }),
              help: __("Display document publication dates", "authdocs"),
            })
          ),

          el(
            PanelBody,
            {
              title: __("Filter & Sort Settings", "authdocs"),
              initialOpen: false,
            },
            el(SelectControl, {
              label: __("Document Restriction", "authdocs"),
              value: restriction,
              options: [
                { label: __("All Documents", "authdocs"), value: "all" },
                {
                  label: __("Restricted Only", "authdocs"),
                  value: "restricted",
                },
                {
                  label: __("Unrestricted Only", "authdocs"),
                  value: "unrestricted",
                },
              ],
              onChange: (value) => setAttributes({ restriction: value }),
              help: __("Filter documents by access restriction", "authdocs"),
            }),
            el(SelectControl, {
              label: __("Sort By", "authdocs"),
              value: orderby,
              options: [
                { label: __("Date", "authdocs"), value: "date" },
                { label: __("Title", "authdocs"), value: "title" },
              ],
              onChange: (value) => setAttributes({ orderby: value }),
              help: __("Sort documents by this field", "authdocs"),
            }),
            el(SelectControl, {
              label: __("Sort Order", "authdocs"),
              value: order,
              options: [
                {
                  label: __("Descending (Newest First)", "authdocs"),
                  value: "DESC",
                },
                {
                  label: __("Ascending (Oldest First)", "authdocs"),
                  value: "ASC",
                },
              ],
              onChange: (value) => setAttributes({ order: value }),
              help: __("Order of sorted documents", "authdocs"),
            })
          )
        ),

        // Block preview
        el(
          "div",
          {
            className: "authdocs-block-preview",
            style: {
              padding: "20px",
              border: "1px dashed #ccc",
              borderRadius: "4px",
              backgroundColor: "#f9f9f9",
              textAlign: "center",
            },
          },
          el(
            "div",
            {
              style: {
                fontSize: "18px",
                fontWeight: "bold",
                marginBottom: "10px",
                color: "#0073aa",
              },
            },
            __("AuthDocs Document Grid", "authdocs")
          ),
          el(
            "div",
            {
              style: {
                fontSize: "14px",
                color: "#666",
                marginBottom: "15px",
              },
            },
            __("Columns:", "authdocs") +
              " " +
              columns +
              " (auto) | " +
              __("Limit:", "authdocs") +
              " " +
              limit +
              " | " +
              __("Pagination:", "authdocs") +
              " " +
              paginationStyle
          ),
          el(
            "div",
            {
              style: {
                fontSize: "12px",
                color: "#999",
                fontStyle: "italic",
              },
            },
            __(
              "Document grid will be displayed here on the frontend",
              "authdocs"
            )
          )
        )
      );
    },

    save: function () {
      // Return null to use server-side rendering
      return null;
    },
  });
})(
  window.wp.blocks,
  window.wp.element,
  window.wp.components,
  window.wp.i18n,
  window.wp.editor
);
