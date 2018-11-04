(function(document, scriptScope){
    if (typeof(scriptScope) === 'undefined') {
        return;
    }

    // Loop through all components that are included on this page
    for (let type in scriptScope.included) {
        // Loop through all instances of this component
        for (let i in scriptScope.included[type]) {
            let instance = scriptScope.included[type][i];
            let context = document.getElementById(instance);

            // If script is marked as singleton make sure to not execute more than once
            if (scriptScope.singleton[type] && scriptScope.executed[type]) {
                continue;
            }

            // Make sure script is defined before executing it
            if (scriptScope.scripts[type]) {
                scriptScope.executed[type] = true;
                scriptScope.scripts[type].call(context, type, instance, 'component-'+type);
                                            // this     name  id         selector
            }
        }
    }
})(document, scriptScope);
