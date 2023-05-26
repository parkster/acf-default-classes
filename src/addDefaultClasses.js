const { useEffect } = wp.element;
const { apiFetch } = wp;
const { select, subscribe, dispatch } = wp.data;
export const addDefaultClasses = (settings, name) => {
    {
        const oldEdit = settings.edit;

        settings.edit = (props) => {
            const { clientId } = props;

            useEffect(() => {
                let previousBlocks = select('core/block-editor').getBlocks();
                const previousBlocksMap = new Map();

                previousBlocks.forEach(block => {
                    previousBlocksMap.set(block.clientId, block.innerBlocks);
                });

                const unsubscribe = subscribe(() => {
                    const currentBlocks = select('core/block-editor').getBlocks();
                    const currentBlocksMap = new Map();

                    currentBlocks.forEach(block => {
                        currentBlocksMap.set(block.clientId, block.innerBlocks);
                    });

                    if (currentBlocks.length > previousBlocks.length) {
                        const addedBlock = currentBlocks.find(
                            (currentBlock) => !previousBlocksMap.has(currentBlock.clientId)
                        );

                        if (addedBlock && addedBlock.name === name) {
                            // Fetch default classes
                            apiFetch({ path: `/acf-default-classes/v1/default-class-list?name=${name}` }).then(data => {
                                // Update className attribute
                                dispatch('core/block-editor').updateBlockAttributes(addedBlock.clientId, { className: data.defaultClassList });
                            });
                        }
                    }

                    // Check for duplicated blocks
                    currentBlocksMap.forEach((currentInnerBlocks, blockClientId) => {
                        const previousInnerBlocks = previousBlocksMap.get(blockClientId);
                        if (previousInnerBlocks && currentInnerBlocks.length > previousInnerBlocks.length) {
                            const duplicatedBlock = currentInnerBlocks.find(
                                (currentBlock) => !previousInnerBlocks.some((previousBlock) => previousBlock.clientId === currentBlock.clientId)
                            );

                            if (duplicatedBlock && duplicatedBlock.name === name) {
                                // Fetch default classes
                                apiFetch({ path: `/acf-default-classes/v1/default-class-list?name=${name}` }).then(data => {
                                    // Update className attribute
                                    dispatch('core/block-editor').updateBlockAttributes(duplicatedBlock.clientId, { className: data.defaultClassList });
                                });
                            }
                        }
                    });

                    previousBlocks = currentBlocks;
                    previousBlocksMap.clear();
                    previousBlocks.forEach(block => {
                        previousBlocksMap.set(block.clientId, block.innerBlocks);
                    });
                });

                return () => {
                    unsubscribe();
                };
            }, [clientId]);

            if (oldEdit) {
                return oldEdit(props);
            }
        };

        return settings;
    }
}
