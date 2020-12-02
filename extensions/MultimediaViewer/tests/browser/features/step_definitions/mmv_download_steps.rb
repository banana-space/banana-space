# encoding: utf-8

When /^I open the download dropdown$/ do
  step 'I click the download icon'
  step 'I click the download down arrow icon'
end

When /^I click the download icon$/ do
  on(E2ETestPage).mmv_download_icon_element.when_present.click
end

When /^I click the download down arrow icon$/  do
  sleep 1
  on(E2ETestPage).mmv_download_down_arrow_icon_element.when_present(10).click
end

When /^I click on the attribution area$/ do
  on(E2ETestPage).mmv_download_attribution_area_element.when_present(10).click
end

When /^I click on the attribution area close icon$/ do
  on(E2ETestPage).mmv_download_attribution_area_close_icon_element.click
end

When /^I click the (.*) download size$/ do |size_option|
  on(E2ETestPage) do |page|
    case size_option
    when 'small'
      @index = 1
    when 'medium'
      @index = 2
    when 'large'
      @index = 3
    when 'extra large'
      @index = 4
    else
      @index = 0
    end

    page.mmv_download_size_options_elements[@index].click
  end
end

When /^the download size options appear$/ do
  on(E2ETestPage).mmv_download_size_menu_element.when_present
end

When /^the download size options disappears$/ do
  on(E2ETestPage).mmv_download_size_menu_element.when_not_present
end

When /^the download menu appears$/ do
  on(E2ETestPage).mmv_download_menu_element.when_present(10)
end

Then /^the download menu should appear$/ do
  expect(on(E2ETestPage).mmv_download_menu_element.when_present(10)).to be_visible
end

Then /^the download menu should disappear$/ do
  expect(on(E2ETestPage).mmv_download_menu_element).not_to be_visible
end

Then /^the original beginning download image size label should be "(.*)"$/ do |size_in_pixels|
  expect(on(E2ETestPage).mmv_download_size_label_element.when_present(10).text).to eq size_in_pixels
end

Then /^the download image size label should be "(.*)"$/ do |size_in_pixels|
  on(E2ETestPage) do |page|
    page.mmv_download_size_options_elements[0].when_not_present
    expect(page.mmv_download_size_label_element.when_present.text).to eq size_in_pixels
  end
end

Then /^the download size options should appear$/ do
  expect(on(E2ETestPage).mmv_download_size_menu_element.when_present).to be_visible
end

Then /^the download links should be the original image$/ do
  on(E2ETestPage) do |page|
    expect(page.mmv_download_link_element.attribute('href')).to match /^?download$/
    expect(page.mmv_download_preview_link_element.attribute('href')).not_to match /^?download$/
    expect(page.mmv_download_link_element.attribute('href')).not_to match %r{/thumb/}
    expect(page.mmv_download_preview_link_element.attribute('href')).not_to match %r{/thumb/}
  end
end

Then /^the download links should be the (\d+) thumbnail$/ do |thumb_size|
  on(E2ETestPage) do |page|
    page.wait_until { page.mmv_download_link_element.attribute('href').match thumb_size }
    expect(page.mmv_download_link_element.attribute('href')).to match /^?download$/
    expect(page.mmv_download_preview_link_element.attribute('href')).not_to match /^?download$/
    expect(page.mmv_download_preview_link_element.attribute('href')).to match thumb_size
  end
end

Then /^the attribution area should be collapsed$/ do
  expect(on(E2ETestPage).mmv_download_attribution_area_element.when_present(10).attribute('class')).to match 'mw-mmv-download-attribution-collapsed'
end

Then /^the attribution area should be open$/ do
  expect(on(E2ETestPage).mmv_download_attribution_area_element.when_present.attribute('class')).not_to match 'mw-mmv-download-attribution-collapsed'
end
