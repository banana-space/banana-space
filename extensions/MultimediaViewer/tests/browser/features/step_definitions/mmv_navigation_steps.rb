# encoding: utf-8

When /^I click the next arrow$/ do
  on(E2ETestPage).mmv_next_button_element.when_present.click
end

When /^I click the previous arrow$/ do
  on(E2ETestPage).mmv_previous_button_element.when_present.click
end

When /^I press the browser back button$/ do
  # $browser.back doesn't work for Safari. This is a workaround for https://code.google.com/p/selenium/issues/detail?id=3771
  on(E2ETestPage).execute_script('window.history.back();')
end

Then /^the image and metadata of the next image should appear$/ do
  on(E2ETestPage) do |page|
    # MMV was launched, article is not visible yet
    expect(page.image1_in_article_element).not_to be_visible
    check_elements_in_viewer_for_image3 page
  end
end

Then /^the image and metadata of the previous image should appear$/ do
  on(E2ETestPage) do |page|
    # MMV was launched, article is not visible yet
    expect(page.image1_in_article_element).not_to be_visible
    check_elements_in_viewer_for_image1 page
  end
end

Then /^the wiki article should be scrolled to the same position as before opening MMV$/ do
  on(E2ETestPage) do |page|
    scroll_difference = page.execute_script('return $(window).scrollTop();') - @article_scroll_top
    expect(scroll_difference.abs).to be < 2
  end
end

Then /^I should be navigated back to the original wiki article$/ do
  on(E2ETestPage) do |page|
    expect(page.image1_in_article_element).to be_visible
    expect(page.mmv_wrapper_element).not_to be_visible
  end
end
