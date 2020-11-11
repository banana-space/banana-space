require 'json'

class CommonsPage
  include PageObject

  page_url 'File:Sunrise_over_fishing_boats_in_Kerala.jpg'

  img(:commons_image, src: /Kerala\.jpg$/)
  div(:mmv_image_loaded_cucumber, class: 'mw-mmv-image-loaded-cucumber')

  def wait_for_image_load(selector)
    browser.execute_script <<-end_script
      function wait_for_image() {
        var $img = $( #{selector.to_json} );
        if ( $img.length
          && $img.attr( 'src' ).match(/Kerala/)
          && !$img.attr( 'src' ).match(/\\/220px-/) // Blurry placeholder
          && $img.prop( 'complete' ) ) {
          $( 'body' ).append( '<div class=\"mw-mmv-image-loaded-cucumber\"/>' );
        } else {
          setTimeout( wait_for_image, 10 );
        }
      }
      wait_for_image();
    end_script

    wait_until { mmv_image_loaded_cucumber_element.exists? }
  end

  def log_performance(stats)
    stats = stats.reject { |_name, value| value.nil? || value.to_s.empty? }
    stats[:duration] = stats[:duration].floor

    browser.execute_script <<-end_script
      mediaWiki.eventLog.declareSchema( 'MultimediaViewerVersusPageFilePerformance',
        { schema:
          { title: 'MultimediaViewerVersusPageFilePerformance',
            properties: {
              type: { type: 'string', required: true, enum: [ 'mmv', 'file-page' ] },
              duration: { type: 'integer', required: true },
              cache: { type: 'string', required: false, enum: [ 'cold', 'warm' ] },
              windowSize: { type: 'string', required: false, enum: [ 'average', 'large'] }
          }
        },
        revision: 7907636
      });

      mw.eventLog.logEvent( 'MultimediaViewerVersusPageFilePerformance', #{stats.to_json} );
    end_script
  end
end
