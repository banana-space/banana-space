# encoding: utf-8

Given /^I am at a wiki article with at least two embedded pictures$/ do
  api.create_page 'MediaViewerE2ETest', File.read(File.join(__dir__, '../../samples/MediaViewerE2ETest.wikitext'))
  visit(E2ETestPage)
  on(E2ETestPage).image1_in_article_element.when_present.should be_visible
end

Given /^the MMV has loaded$/ do
  on(E2ETestPage) do |page|
    page.wait_until do
      # Wait for JS to hijack standard link
      # TODO: If this approach works well, we should implement general
      # `wait_for_resource` and `resource_ready?` helper methods in
      # mw-selenium, and document this pattern on mw.org
      browser.execute_script("return mw.loader.getState('mmv.bootstrap') === 'ready'")
    end
  end
end

Given /^I am viewing an image using MMV$/ do
  step 'I am at a wiki article with at least two embedded pictures'
  step 'the MMV has loaded'
  step 'I click on the second image in the article'
  step 'the image metadata and the image itself should be there'
end

When /^I click on the first image in the article$/ do
  on(E2ETestPage) do |page|
    # We store the offset of the image as the scroll position and scroll to it, because cucumber/selenium
    # sometimes automatically scrolls to it when we ask it to click on it (seems to depend on timing)
    @article_scroll_top = page.execute_script("var scrollTop = Math.round($('a[href$=\"File:Sunrise_over_fishing_boats_in_Kerala.jpg\"]').first().find('img').offset().top); window.scrollTo(0, scrollTop); return scrollTop;")
    # Scrolls to the image and clicks on it
    page.image1_in_article
    # This is a global variable that can be used to measure performance
    @image_click_time = Time.now.getutc
  end
end

When /^I click on the second image in the article$/ do
  on(E2ETestPage) do |page|
    # We store the offset of the image as the scroll position and scroll to it, because cucumber/selenium
    # sometimes automatically scrolls to it when we ask it to click on it (seems to depend on timing)
    @article_scroll_top = page.execute_script("var scrollTop = Math.round($('a[href$=\"File:Wikimedia_Foundation_2013_All_Hands_Offsite_-_Day_2_-_Photo_24.jpg\"]').first().find('img').offset().top); window.scrollTo(0, scrollTop); return scrollTop;")
    # Scrolls to the image and clicks on it
    page.image2_in_article
    # This is a global variable that can be used to measure performance
    @image_click_time = Time.now.getutc
  end
end

When /^I close MMV$/ do
  on(E2ETestPage).mmv_close_button_element.when_present(30).click
end

When /^I click the image$/  do
  on(E2ETestPage) do
    # Clicking the top-left corner of the image is necessary for the test to work on IE
    # A plain click on the image element ends up hitting the dialog, which means it won't close
    begin
      browser.driver.action.move_to(browser.driver.find_element(:class, 'mw-mmv-image'), 10, 10).click.perform
    rescue
      # Plain click for web drivers that don't support mouse moves (Safari, currently)
      on(E2ETestPage).mmv_image_div_element.when_present.click
    end
  end
end

Then /^the image metadata and the image itself should be there$/ do
  on(E2ETestPage) do |page|
    # MMV was launched, article is not visible now
    page.image1_in_article_element.should_not be_visible
    check_elements_in_viewer_for_image2 page
  end
end

# Helper function that verifies the presence of various elements in viewer
# while looking at image1 (Kerala)
def check_elements_in_viewer_for_image1(page)
  # Check basic MMV elements are present
  expect(page.mmv_overlay_element.when_present).to be_visible
  expect(page.mmv_wrapper_element.when_present).to be_visible
  expect(page.mmv_image_div_element).to be_visible

  # Check image content
  expect(page.mmv_final_image_element.when_present.attribute('src')).to match /Kerala/

  # Check basic metadata is present

  # Title
  expect(page.mmv_metadata_title_element.when_present.text).to match /^Sunrise over fishing boats$/
  # License
  expect(page.mmv_metadata_license_element.when_present.attribute('href')).to match %r{^https?://creativecommons.org/licenses/by-sa/3.0$}
  expect(page.mmv_metadata_license_element.when_present.text).to match 'CC BY-SA 3.0'
  # Credit
  expect(page.mmv_metadata_credit_element.when_present).to be_visible
  expect(page.mmv_metadata_source_element.when_present.text).to match 'Own work'

  # Image metadata
  expect(page.mmv_image_metadata_wrapper_element.when_present).to be_visible
  # Description
  expect(page.mmv_image_metadata_desc_element.when_present.text).to match 'Sunrise over fishing boats on the beach south of Kovalam'
  # Image metadata links
  expect(page.mmv_image_metadata_links_wrapper_element.when_present).to be_visible
  # Details link
  expect(page.mmv_details_page_link_element.when_present.text).to match 'More details'
  expect(page.mmv_details_page_link_element.when_present.attribute('href')).to match /boats_in_Kerala.jpg$/
end

# Helper function that verifies the presence of various elements in viewer
# while looking at image2 (Aquarium)
def check_elements_in_viewer_for_image2(page)
  # Check basic MMV elements are present
  expect(page.mmv_overlay_element.when_present).to be_visible
  expect(page.mmv_wrapper_element.when_present).to be_visible
  expect(page.mmv_image_div_element).to be_visible

  # Check image content
  expect(page.mmv_final_image_element.when_present(30).attribute('src')).to match 'Offsite'

  # Check basic metadata is present

  # Title
  expect(page.mmv_metadata_title_element.when_present.text).to match /^Tropical Fish Aquarium$/
  # License
  expect(page.mmv_metadata_license_element.when_present(10).attribute('href')).to match %r{^https?://creativecommons.org/licenses/by-sa/3.0$}
  expect(page.mmv_metadata_license_element.when_present.text).to match 'CC BY-SA 3.0'
  # Credit
  expect(page.mmv_metadata_credit_element.when_present).to be_visible
  expect(page.mmv_metadata_source_element.when_present.text).to match 'Wikimedia Foundation'

  # Image metadata
  expect(page.mmv_image_metadata_wrapper_element.when_present).to be_visible
  # Description
  expect(page.mmv_image_metadata_desc_element.when_present.text).to match 'Photo from Wikimedia Foundation'
  # Image metadata links
  expect(page.mmv_image_metadata_links_wrapper_element.when_present).to be_visible
  # Details link
  expect(page.mmv_details_page_link_element.when_present.text).to match 'More details'
  expect(page.mmv_details_page_link_element.when_present.attribute('href')).to match /All_Hands_Offsite.*\.jpg$/
end

# Helper function that verifies the presence of various elements in viewer
# while looking at image3 (Hong Kong)
def check_elements_in_viewer_for_image3(page)
  # Check basic MMV elements are present
  expect(page.mmv_overlay_element.when_present).to be_visible
  expect(page.mmv_wrapper_element.when_present).to be_visible
  expect(page.mmv_image_div_element).to be_visible

  # Check image content
  expect(page.mmv_image_div_element.image_element.attribute('src')).to match 'Hong_Kong'

  # Check basic metadata is present

  # Title
  expect(page.mmv_metadata_title_element.when_present.text).to match /^Hong Kong Harbor at night$/
  # License
  expect(page.mmv_metadata_license_element.when_present.attribute('href')).to match %r{^https?://creativecommons.org/licenses/by-sa/3.0$}
  expect(page.mmv_metadata_license_element.when_present.text).to match 'CC BY-SA 3.0'
  # Credit
  expect(page.mmv_metadata_credit_element.when_present).to be_visible
  expect(page.mmv_metadata_source_element.when_present.text).to match 'Wikimedia Foundation'

  # Image metadata
  expect(page.mmv_image_metadata_wrapper_element.when_present).to be_visible
  # Description
  expect(page.mmv_image_metadata_desc_element.when_present.text).to match /Photos from our product team's talks at Wikimania 2013 in Hong Kong./
  # Image metadata links
  expect(page.mmv_image_metadata_links_wrapper_element.when_present).to be_visible
  # Details link
  expect(page.mmv_details_page_link_element.when_present.text).to match 'More details'
  expect(page.mmv_details_page_link_element.when_present.attribute('href')).to match /Wikimania_2013_-_Hong_Kong_-_Photo_090\.jpg$/
end
