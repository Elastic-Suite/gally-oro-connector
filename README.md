# Gally Plugin for Oro Commerce

## Installation

Install this bundle with composer:

```shell
composer require gally/oro-plugin:dev-master
```

## Usage

- In oro backend, you can enable gally and add credentials from configuration screen.
- Run this command to sync your catalog structure with Gally :
    ```shell
        bin/console gally:structure-sync # Sync catalog and source field data with gally
    ```
- Run a full index from Oro to Gally.
    ```shell
        bin/console oro:website-search:reindex # Index category and product entity to gally
    ```
- At this step, you should be able to see your product and source field in the Gally backend.
- They should also appear in your Oro frontend when searching or browsing categories.
- And you're done !

## Devlopement

If you need to update indexation behavior, you need to test various way to run the reindex process in oro
Fisrt you need to test various combinations of these options of the reindex command :
```shell
bin/console oro:website-search:reindex
bin/console oro:website-search:reindex --scheduled # Async reindex with message queue
bin/console oro:website-search:reindex --website-id=3 # Specify the websites you want to reindex
bin/console oro:website-search:reindex --ids=1000-2000 # Specify the ids range you want to reindex
bin/console oro:website-search:reindex --class="Oro\Bundle\WebCatalogBundle\Entity\ContentNode" # Specify the entities you want to reindex
```

Then you need to test update of product of contentNode from oro backend (you need to have an active consumer for this).
You can run a consumer with the command `bin/console oro:message-queue:consume`.

