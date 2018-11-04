<template lang="php">
    <div id="myElement">
        @if ($aboo)
            <h1>My Heading</h1>
        @endif
    </div>
</template>

<style lang="scss">
    $myColor: #00ff00;

    #myElement {
        color: $myColor;
    }
</style>

<script singleton>
    console.log('Component');
</script>
