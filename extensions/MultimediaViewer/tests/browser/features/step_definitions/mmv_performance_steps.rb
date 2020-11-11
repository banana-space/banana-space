When /^I click on an unrelated image in the article to warm up the browser cache$/ do
  on(E2ETestPage).other_image_in_article
end

Given /^I visit the Commons page$/ do
  @commons_open_time = Time.now.getutc
  browser.goto 'https://commons.wikimedia.org/wiki/File:Sunrise_over_fishing_boats_in_Kerala.jpg'
end

Given /^I visit an unrelated Commons page to warm up the browser cache$/ do
  browser.goto 'https://commons.wikimedia.org/wiki/File:Wikimedia_Foundation_2013_All_Hands_Offsite_-_Day_2_-_Photo_16.jpg'
end

Given /^I have a small browser window$/ do
  browser.window.resize_to 900, 700
end

Given /^I have an average browser window$/ do
  browser.window.resize_to 1366, 768
end

Given /^I have a large browser window$/ do
  browser.window.resize_to 1920, 1080
end

Given /^I am using a custom user agent$/ do
  browser_factory.override(browser_user_agent: env[:browser_useragent])
end

Then /^the File: page image is loaded$/ do
  on(CommonsPage) do |page|
    page.wait_for_image_load '.fullImageLink img'
    # Has to be a global variable, otherwise it doesn't survive between scenarios
    $commons_time = Time.now.getutc - @commons_open_time
    page.log_performance type: 'file-page', duration: $commons_time * 1000
  end
end

Then /^the MMV image is loaded in (\d+) percent of the time with a (.*) cache and an? (.*) browser window$/ do |percentage, cache, window_size|
  on(E2ETestPage) do |page|
    page.wait_for_image_load '.mw-mmv-image img'
    mmv_time = Time.now.getutc - @image_click_time
    page.log_performance type: 'mmv', duration: mmv_time * 1000, cache: cache, windowSize: window_size

    expected_time = $commons_time * (percentage.to_f / 100.0)
    expect(mmv_time).to be < expected_time
  end
end
