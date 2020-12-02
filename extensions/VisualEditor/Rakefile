require 'bundler/setup'

require 'rubocop/rake_task'
RuboCop::RakeTask.new(:rubocop) do |task|
  # If you use mediawiki-vagrant, rubocop will by default use its .rubocop.yml.

  # This line makes it explicit that you want .rubocop.yml from the directory
  # where `bundle exec rake` is executed:
  task.options = ['-c', '.rubocop.yml']
end

task :default => [:test]

desc 'Run all build/tests commands (CI entry point)'
task :test => [:rubocop]

desc 'Upload screenshots to commons.wikimedia.org'
task :commons_upload do
  require 'commons_upload'
  required_envs = %w[
    MEDIAWIKI_API_UPLOAD_URL
    MEDIAWIKI_USER
    MEDIAWIKI_PASSWORD
  ]
  has_all_envs = required_envs.all? { |env| ENV.key?(env) }
  fail "Requires env variables:\n#{required_envs}" unless has_all_envs
  CommonsUpload.images
end
