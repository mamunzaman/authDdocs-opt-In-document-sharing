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
  registerBlockType("protecteddocs/document-grid", {
    title: protecteddocs_block.title,
    description: protecteddocs_block.description,
    icon: protecteddocs_block.icon,
    category: "widgets",
    keywords: [
      __("documents", "authdocs"),
      __("grid", "authdocs"),
      __("authdocs", "authdocs"),
    ],
    attributes: {
      columns: {
        type: "number",
        default: 3,
      },
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
      colorPalette: {
        type: "string",
        default: "default",
      },
      columnsDesktop: {
        type: "number",
        default: 5,
      },
      columnsTablet: {
        type: "number",
        default: 3,
      },
      columnsMobile: {
        type: "number",
        default: 1,
      },
    },

    edit: function (props) {
      const { attributes, setAttributes } = props;

      // Ensure attributes exist with fallbacks
      const {
        columns = 3,
        limit = 12,
        loadMoreLimit = 12,
        paginationType = "classic",
        featuredImage = true,
        paginationStyle = "classic",
        restriction = "all",
        showDescription = true,
        showDate = true,
        orderby = "date",
        order = "DESC",
        colorPalette = "default",
        columnsDesktop = 5,
        columnsTablet = 3,
        columnsMobile = 1,
      } = attributes;

      // Use the columns setting directly

      // Handle pagination style changes
      const handlePaginationStyleChange = (value) => {
        try {
          setAttributes({ paginationStyle: value });

          // Auto-adjust pagination type based on style
          if (value === "load_more") {
            setAttributes({ paginationType: "ajax" });
          } else if (value === "classic") {
            setAttributes({ paginationType: "classic" });
          }
        } catch (error) {
          console.error("AuthDocs Block Error:", error);
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
            // Responsive Columns settings
            el(NumberControl, {
              label: __("Desktop Columns", "authdocs"),
              value: columnsDesktop,
              onChange: (value) => {
                try {
                  setAttributes({
                    columnsDesktop: parseInt(value) || 5,
                  });
                } catch (error) {
                  console.error("AuthDocs Block Error:", error);
                }
              },
              min: 1,
              max: 6,
              help: __(
                "Number of columns on desktop screens (1280px+)",
                "authdocs"
              ),
            }),
            el(NumberControl, {
              label: __("Tablet Columns", "authdocs"),
              value: columnsTablet,
              onChange: (value) => {
                try {
                  setAttributes({
                    columnsTablet: parseInt(value) || 3,
                  });
                } catch (error) {
                  console.error("AuthDocs Block Error:", error);
                }
              },
              min: 1,
              max: 4,
              help: __(
                "Number of columns on tablet screens (768px-1279px)",
                "authdocs"
              ),
            }),
            el(NumberControl, {
              label: __("Mobile Columns", "authdocs"),
              value: columnsMobile,
              onChange: (value) => {
                try {
                  setAttributes({
                    columnsMobile: parseInt(value) || 1,
                  });
                } catch (error) {
                  console.error("AuthDocs Block Error:", error);
                }
              },
              min: 1,
              max: 2,
              help: __(
                "Number of columns on mobile screens (below 768px)",
                "authdocs"
              ),
            }),
            // Documents per page
            el(NumberControl, {
              label: __("Documents per page", "authdocs"),
              value: limit,
              onChange: (value) => {
                try {
                  setAttributes({
                    limit: parseInt(value) || 12,
                  });
                } catch (error) {
                  console.error("AuthDocs Block Error:", error);
                }
              },
              min: 1,
              max: 100,
              help: __("Number of documents to display per page", "authdocs"),
            }),
            // Load more limit
            el(NumberControl, {
              label: __("Load more limit", "authdocs"),
              value: loadMoreLimit,
              onChange: (value) => {
                try {
                  setAttributes({
                    loadMoreLimit: parseInt(value) || 12,
                  });
                } catch (error) {
                  console.error("AuthDocs Block Error:", error);
                }
              },
              min: 1,
              max: 50,
              help: el(
                "div",
                {
                  style: {
                    marginTop: "8px",
                    padding: "12px",
                    backgroundColor: "#f8f9fa",
                    border: "1px solid #e9ecef",
                    borderRadius: "6px",
                    fontSize: "13px",
                    lineHeight: "1.4",
                    color: "#495057",
                  },
                },
                el(
                  "strong",
                  {
                    style: {
                      color: "#212529",
                      display: "block",
                      marginBottom: "4px",
                    },
                  },
                  __("Load More Behavior:", "authdocs")
                ),
                __(
                  'Number of additional documents to load when "Load More" is clicked',
                  "authdocs"
                )
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
            el(SelectControl, {
              label: __("Color Palette", "authdocs"),
              value: colorPalette,
              options: [
                {
                  label: __("Use Frontend Settings", "authdocs"),
                  value: "default",
                },
                {
                  label: __("Black & White + Blue", "authdocs"),
                  value: "black_white_blue",
                },
                {
                  label: __("Black & Gray", "authdocs"),
                  value: "black_gray",
                },
              ],
              onChange: (value) => setAttributes({ colorPalette: value }),
              help: __("Choose a color palette for this block", "authdocs"),
            }),
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
                display: "flex",
                alignItems: "center",
                fontSize: "18px",
                fontWeight: "bold",
                marginBottom: "10px",
                color: "#0073aa",
              },
            },
            el(
              "span",
              {
                style: {
                  display: "inline-block",
                  width: "24px",
                  height: "24px",
                  marginRight: "8px",
                  background:
                    "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
                  borderRadius: "4px",
                  position: "relative",
                },
              },
              el(
                "span",
                {
                  style: {
                    position: "absolute",
                    top: "50%",
                    left: "50%",
                    transform: "translate(-50%, -50%)",
                    color: "white",
                    fontSize: "14px",
                    fontWeight: "bold",
                  },
                },
                "📄"
              )
            ),
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
              columnsDesktop +
              "/" +
              columnsTablet +
              "/" +
              columnsMobile +
              " | " +
              __("Limit:", "authdocs") +
              " " +
              limit +
              " | " +
              __("Pagination:", "authdocs") +
              " " +
              paginationStyle +
              " | " +
              __("Colors:", "authdocs") +
              " " +
              (colorPalette === "default"
                ? __("Frontend Settings", "authdocs")
                : colorPalette.replace(/_/g, " "))
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
