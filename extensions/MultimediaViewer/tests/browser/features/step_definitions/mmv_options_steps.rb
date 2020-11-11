# encoding: utf-8

When /^I click the options icon$/ do
  on(E2ETestPage).mmv_options_icon_element.click
end

Then /^the options menu should appear with the prompt to disable$/ do
  on(E2ETestPage).mmv_options_menu_disable_element.should be_visible
end

Then /^the options menu should disappear$/ do
  on(E2ETestPage).mmv_options_menu_disable_element.should_not be_visible
end

When /^I click the enable button$/ do
  on(E2ETestPage).mmv_options_enable_button_element.click
end

When /^I click the disable button$/ do
  on(E2ETestPage).mmv_options_disable_button_element.click
end

When /^I click the disable cancel button$/ do
  on(E2ETestPage).mmv_options_disable_cancel_button_element.click
end

When /^I click the enable X icon$/ do
  on(E2ETestPage).mmv_options_enable_x_icon_element.click
end

When /^I click the disable X icon$/ do
  on(E2ETestPage).mmv_options_disable_x_icon_element.click
end

When /^I disable MMV$/ do
  step 'I click the options icon'
  step 'I click the disable button'
end

When /^I reenable MMV$/ do
  step 'I disable MMV'
  step 'I click the options icon'
  step 'I click the enable button'
end

When /^I click the options icon with MMV disabled$/ do
  step 'I disable MMV'
  step 'I click the options icon'
end

When /^I disable and close MMV$/ do
  step 'I disable MMV'
  step 'I close MMV'
end

Then /^the disable confirmation should appear$/ do
  on(E2ETestPage).mmv_options_disable_confirmation_element.should be_visible
end

Then /^the enable confirmation should appear$/ do
  on(E2ETestPage).mmv_options_enable_confirmation_element.should be_visible
end

Then /^I am taken to the file page$/ do
  on(E2ETestPage) do |page|
    page.current_url.should match %r{/File:}
    page.current_url.should_not match %r{#/media}
  end
end
