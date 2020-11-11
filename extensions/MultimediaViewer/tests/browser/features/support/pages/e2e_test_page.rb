class E2ETestPage < CommonsPage
  include PageObject

  page_url 'MediaViewerE2ETest'

  # Tag page elements that we will need.

  # First image in lightbox demo page
  a(:image1_in_article, class: 'image', href: /Kerala\.jpg$/)
  a(:image2_in_article, class: 'image', href: /Wikimedia_Foundation_2013_All_Hands_Offsite_-_Day_2_-_Photo_24\.jpg$/)

  a(:other_image_in_article, href: /Academy_of_Sciences\.jpg$/)

  # Black overlay
  div(:mmv_overlay, class: 'mw-mmv-overlay')

  # Wrapper div for all mmv elements
  div(:mmv_wrapper, class: 'mw-mmv-wrapper')

  # Wrapper div for image
  div(:mmv_image_div, class: 'mw-mmv-image')

  # Actual image
  image(:mmv_final_image, class: 'mw-mmv-final-image')

  # Metadata elements
  span(:mmv_metadata_title, class: 'mw-mmv-title')
  a(:mmv_metadata_license, class: 'mw-mmv-license')
  p(:mmv_metadata_credit, class: 'mw-mmv-credit')
  span(:mmv_metadata_source, class: 'mw-mmv-source')

  div(:mmv_image_metadata_wrapper, class: 'mw-mmv-image-metadata')
  p(:mmv_image_metadata_desc, class: 'mw-mmv-image-desc')

  ul(:mmv_image_metadata_links_wrapper, class: 'mw-mmv-image-links')
  a(:mmv_details_page_link, class: 'mw-mmv-description-page-button')

  # Controls
  button(:mmv_next_button, class: 'mw-mmv-next-image')
  button(:mmv_previous_button, class: 'mw-mmv-prev-image')
  button(:mmv_close_button, class: 'mw-mmv-close')
  div(:mmv_image_loaded_cucumber, class: 'mw-mmv-image-loaded-cucumber')

  # Download
  button(:mmv_download_icon, class: 'mw-mmv-download-button')
  div(:mmv_download_menu, class: 'mw-mmv-download-dialog')
  span(:mmv_download_size_label, class: 'mw-mmv-download-image-size')
  span(:mmv_download_down_arrow_icon, class: 'mw-mmv-download-select-menu')
  div(:mmv_download_size_menu_container, class: 'mw-mmv-download-size')
  div(:mmv_download_size_menu) do |page|
    page.mmv_download_size_menu_container_element.div_element(class: 'oo-ui-selectWidget')
  end
  divs(:mmv_download_size_options, class: 'oo-ui-menuOptionWidget')
  a(:mmv_download_link, class: 'mw-mmv-download-go-button')
  a(:mmv_download_preview_link, class: 'mw-mmv-download-preview-link')
  div(:mmv_download_attribution_area, class: 'mw-mmv-download-attribution')
  p(:mmv_download_attribution_area_close_icon, class: 'mw-mmv-download-attribution-close-button')
  div(:mmv_download_attribution_area_input_container, class: 'mw-mmv-download-attr-input')
  text_field(:mmv_download_attribution_area_input) do |page|
    page.mmv_download_attribution_area_input_container_element.text_field_element
  end

  # Options
  button(:mmv_options_icon, class: 'mw-mmv-options-button')
  div(:mmv_options_menu_disable, class: 'mw-mmv-options-disable')
  div(:mmv_options_menu_enable, class: 'mw-mmv-options-enable')
  button(:mmv_options_enable_button) do |page|
    page.mmv_options_menu_enable_element.div_element(class: 'mw-mmv-options-submit').button_element(class: 'mw-mmv-options-submit-button')
  end
  button(:mmv_options_disable_button) do |page|
    page.mmv_options_menu_disable_element.div_element(class: 'mw-mmv-options-submit').button_element(class: 'mw-mmv-options-submit-button')
  end
  button(:mmv_options_enable_cancel_button) do |page|
    page.mmv_options_menu_enable_element.div_element(class: 'mw-mmv-options-submit').button_element(class: 'mw-mmv-options-cancel-button')
  end
  button(:mmv_options_disable_cancel_button) do |page|
    page.mmv_options_menu_disable_element.div_element(class: 'mw-mmv-options-submit').button_element(class: 'mw-mmv-options-cancel-button')
  end
  div(:mmv_options_disable_confirmation, class: 'mw-mmv-disable-confirmation')
  div(:mmv_options_disable_x_icon) do |page|
    page.mmv_options_disable_confirmation_element.div_element(class: 'mw-mmv-confirmation-close')
  end
  div(:mmv_options_enable_confirmation, class: 'mw-mmv-enable-confirmation')
  div(:mmv_options_enable_x_icon) do |page|
    page.mmv_options_enable_confirmation_element.div_element(class: 'mw-mmv-confirmation-close')
  end
end
