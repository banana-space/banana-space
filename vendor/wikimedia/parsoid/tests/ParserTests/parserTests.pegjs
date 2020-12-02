/**
 * PEG.js grammar for reading MediaWiki parser tests files
 * 2011-07-20 Brion Vibber <brion@pobox.com>
 */

testfile =
    format?
    chunk+



eol = "\n"

whitespace = [ \t]+

ws = whitespace

rest_of_line = c:([^\n]*) eol
{
    return c.join('');
}

line = (!"!!") line:rest_of_line
{
    return line;
}

text = lines:line*
{
    return lines.join('\n');
}

chunk =
    comment /
    article /
    test /
    line /
    hooks /
    functionhooks

format =
    "!!" ws? version_keyword ws+ v:$([0-9]+) rest_of_line
{
    return {
        type: 'version',
        text: v
    }
}

version_keyword = [vV] [eE] [rR] [sS] [iI] [oO] [nN]

comment =
    "#" text:rest_of_line
{
    return {
        type: 'comment',
        comment: text
    }
}

empty =
    eol /
    ws eol
{
    return {
        type: 'empty'
    }
}



article =
    start_article title:line start_text text:text end_article
{
    return {
        type: 'article',
        title: title,
        text: text
    }
}

start_article =
    "!!" ws? "article" ws? eol

start_text =
    "!!" ws? "text" ws? eol

end_article =
    "!!" ws? "endarticle" ws? eol

// function hooks

functionhooks = start_functionhooks text:text end_functionhooks
{
    return {
        type: 'functionhooks',
        text: text
    }
}

start_functionhooks =
    "!!" ws? "functionhooks" ":"? ws? eol

end_functionhooks =
    "!!" ws? "endfunctionhooks" ":"? ws? eol

end_test =
    "!!" ws? "end" ws? eol

test =
    start_test
    title:text
    sections:(section / option_section)*
    end_test
{
    var test = {
        type: 'test',
        title: title
    };
    for (var i = 0; i < sections.length; i++) {
        var section = sections[i];
        test[section.name] = section.text;
    }
    // pegjs parser handles item options as follows:
    //   item option             value of item.options.parsoid
    //    <none>                          undefined
    //    parsoid                             ""
    //    parsoid=wt2html                  "wt2html"
    //    parsoid=wt2html,wt2wt        ["wt2html","wt2wt"]
    //    parsoid={"modes":["wt2wt"]}    {modes:['wt2wt']}

    // treat 'parsoid=xxx,yyy' in options section as shorthand for
    // 'parsoid={modes:["xxx","yyy"]}'
    if ( test.options && test.options.parsoid ) {
        if (test.options.parsoid === '') {
            test.options.parsoid = {};
        }
        if (typeof test.options.parsoid === 'string') {
            test.options.parsoid = [ test.options.parsoid ];
        }
        if (Array.isArray(test.options.parsoid)) {
            test.options.parsoid = { modes: test.options.parsoid };
        }
    }
    return test;
}

section =
    "!!" ws? (!"end") (!"options") name:(c:[^ \t\r\n]+ { return c.join(''); }) rest_of_line
    text:text
{
    return {
        name: name,
        text: text
    };
}

option_section =
    "!!" ws? "options" ws? eol
    opts:option_list?
{
    var o = {};
    if (opts && opts.length) {
        for (var i = 0; i < opts.length; i++) {
            o[opts[i].k] = opts[i].v || '';
        }
    }
    return {
        name: "options",
        text: o
    };
}

option_list = o:an_option [ \t\n]+ rest:option_list?
{
    var result = [ o ];
    if (rest && rest.length) {
        result.push.apply(result, rest);
    }
    return result;
}

// from PHP parser in tests/parser/parserTest.inc:parseOptions()
//   foo
//   foo=bar
//   foo="bar baz"
//   foo=[[bar baz]]
//   foo={...json...}
//   foo=bar,"baz quux",[[bat]]
an_option = k:option_name v:option_value?
{
    return {k:k.toLowerCase(), v:(v||'')};
}

option_name = c:[^ \t\n=!]+
{
    return c.join('');
}

option_value = ws? "=" ws? ovl:option_value_list
{
    return (ovl.length===1) ? ovl[0] : ovl;
}

option_value_list = v:an_option_value
                    rest:( ws? "," ws? ovl:option_value_list {return ovl; })?
{
    var result = [ v ];
    if (rest && rest.length) {
        result.push.apply(result, rest);
    }
    return result;
}

an_option_value = v:(link_target_value / quoted_value / plain_value / json_value)
{
    if (v[0]==='\"' || v[0]==='{') { // } is needed to make pegjs happy
        return JSON.parse(v);
    }
    return v;
}

link_target_value = "[[" v:[^\]]* "]]"
{
    // Perhaps we should canonicalize the title?
    // Protect with JSON.stringify just in case the link target starts with
    // double-quote or open-brace.
    return JSON.stringify(v.join(''));
}

quoted_value = [\"] v:( [^\\\"] / ("\\" c:. { return "\\"+c; } ) )* [\"]
{
    return '\"' + v.join('') + '\"';
}

plain_value = v:[^ \t\n\"\'\[\]=,!\{]+
{
    return v.join('');
}

json_value = "{" v:( [^\"\{\}] / quoted_value / json_value )* "}"
{
    return "{" + v.join('') + "}";
}


start_test =
    "!!" ws? "test" ws? eol

end_test =
    "!!" ws? "end" ws? eol


hooks =
    start_hooks text:text end_hooks
{
    return {
        type: 'hooks',
        text: text
    }
}

start_hooks =
    "!!" ws? "hooks" ":"? ws? eol

end_hooks =
    "!!" ws? "endhooks" ws? eol
