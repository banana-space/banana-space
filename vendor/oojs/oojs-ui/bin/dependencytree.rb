require 'pp'
require_relative 'docparser'

$classes = parse_any_path 'src'

def prefix lines
	if lines.empty?
		''
	else
		lines.gsub(/^/, '- ')
	end
end

def find_class klass_name
	$classes.find{|c| c[:name] == klass_name }
end

def describe klass_name
	out = []
	out << klass_name
	klass = find_class klass_name
	if klass
		if klass[:parent]
			out.push prefix describe klass[:parent]
		end
		if klass[:mixins]
			klass[:mixins].each do |mixin|
				out.push prefix describe mixin
			end
		end
	end
	out.select{|a| !a.empty? }.join "\n"
end

$classes.sort_by!{|klass|
	# sort by "type" first (widget/layout/element/etc.), then by name
	klass[:name].split(/(?=Layout|Widget|Element|Dialog|Tool|Theme)/).reverse
}

$classes.each{|klass|
	puts describe klass[:name]
}
